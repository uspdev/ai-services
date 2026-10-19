# Serviço de Formatação e Normalização Bibliográfica

Este documento detalha o funcionamento, os contratos de dados, as validações e a interface do serviço de formatação bibliográfica. Para a arquitetura base (configuração do SDK, LiteLLM, `AiService`, cache, versionamento de prompts e tratamento de falhas), consulte a [Arquitetura Geral](implementacao-laravel-ai-sdk.md).

## Índice

- [1. Visão geral e arquivos do módulo](#1-visão-geral-e-arquivos-do-módulo)
- [2. Rota, controller e validação de entrada](#2-rota-controller-e-validação-de-entrada)
- [3. Agente de bibliografia (`BibliografiaAgent`)](#3-agente-de-bibliografia-bibliografiaagent)
- [4. Schema estruturado e validação de correspondência](#4-schema-estruturado-e-validação-de-correspondência)
- [5. Integração com o acervo Dedalus (`DedalusService`)](#5-integração-com-o-acervo-dedalus-dedalusservice)
- [6. Contrato público (Web e JSON)](#6-contrato-público-web-e-json)
- [7. Apresentação na interface web](#7-apresentação-na-interface-web)
- [8. Testes do módulo](#8-testes-do-módulo)

---

## 1. Visão geral e arquivos do módulo

O módulo de bibliografia recebe uma lista de referências em texto livre e uma norma acadêmica selecionada pelo usuário, devolvendo as referências padronizadas, as explicações das correções aplicadas, os metadados para busca no catálogo Dedalus e o índice de confiança de cada referência original.

| Arquivo | Responsabilidade |
| --- | --- |
| [app/Ai/Agents/BibliografiaAgent.php](../app/Ai/Agents/BibliografiaAgent.php) | Define `SLUG = 'bibliografia'`, normas suportadas (`standards()`), template padrão (`defaultTemplate()`), schema das quatro listas e validação de correspondência. |
| [app/Services/DedalusService.php](../app/Services/DedalusService.php) | Monta o termo de busca (`autor` + `titulo`), consulta o endpoint `WSearch.php` do catálogo Dedalus/ABCD com cache e normaliza as publicações encontradas. |
| [app/Http/Controllers/BibliografiaController.php](../app/Http/Controllers/BibliografiaController.php) | Atende `GET/POST /bibliografia` (`bibliografia.index`) e `GET /bibliografia/dedalus` (`bibliografia.dedalus`), executa o agente no `AiService`, consulta o `DedalusService` e retorna a view ou JSON. |
| [resources/views/bibliografia/index.blade.php](../resources/views/bibliografia/index.blade.php) | Exibe o formulário, o comparador de alterações (diff), as referências corrigidas editáveis e as explicações com badges de confiança e de resultados no Dedalus. |
| [resources/views/bibliografia/partials/dedalus.blade.php](../resources/views/bibliografia/partials/dedalus.blade.php) | Renderiza os modais de consulta ao Dedalus para cada referência e o script `UIDedalus` para refinamento assíncrono do termo de busca. |
| [resources/views/bibliografia/partials/form.blade.php](../resources/views/bibliografia/partials/form.blade.php) | Formulário de envio para `route('bibliografia.index')`. |
| [tests/Feature/BibliografiaAgentTest.php](../tests/Feature/BibliografiaAgentTest.php) | Verifica interpolação das normas, schema e validação de correspondência das listas. |
| [tests/Feature/DedalusServiceTest.php](../tests/Feature/DedalusServiceTest.php) | Verifica construção do termo de busca, normalização de publicações, cache, tolerância a falhas e o endpoint `bibliografia.dedalus`. |

---

## 2. Rota, controller e validação de entrada

As rotas estão registradas em [routes/web.php](../routes/web.php) sob o middleware `auth`:

| Endpoint | Nome da rota | Controller | Parâmetros / Campos obrigatórios |
| --- | --- | --- | --- |
| `GET/POST /bibliografia` | `bibliografia.index` | `BibliografiaController@index` | No POST: `message` (string de até 10.000 caracteres) e `standard` (uma das normas retornadas por `BibliografiaAgent::standards()`). |
| `GET /bibliografia/dedalus` | `bibliografia.dedalus` | `BibliografiaController@dedalus` | Na query string: `search` (string obrigatória de até 500 caracteres) e `library` (opcional, até 50 caracteres). |

- **`GET /bibliografia`**:
  - Na web: renderiza `bibliografia.index` com a lista de normas disponíveis (`$standards`).
  - No JSON (`Accept: application/json`): retorna HTTP 200 com a instrução `Envie uma requisição POST com os campos "message" e "standard" para processar.`.
- **`POST /bibliografia`**:
  - Valida `message` e `standard` antes de acionar a IA (retornando HTTP 422 no JSON caso inválido).
  - Instancia `new BibliografiaAgent($standard)` e envia `$referencias` ao `AiService::submit()`.
  - Prepara os termos de busca do Dedalus via `DedalusService::prepareBatch($resultado['busca_dedalus'])` sem realizar chamadas HTTP síncronas, preservando o tempo de resposta da IA.
- **`GET /bibliografia/dedalus`**:
  - Acionado automaticamente via AJAX (`UIDedalus`) assim que a tela de resultados é carregada, bem como quando o usuário edita o termo de busca ou a sigla da biblioteca diretamente pelo modal da interface.

---

## 3. Agente de bibliografia (`BibliografiaAgent`)

### 3.1 Normas bibliográficas (`standards()`)

`BibliografiaAgent::standards()` consulta primeiro a chave `metadata['standards']` da versão ativa do prompt `'bibliografia'` na tabela `ai_prompts`. Caso não haja customização no banco de dados, utiliza a constante `BibliografiaAgent::DEFAULT_STANDARDS`:

- `ABNT NBR 6023:2025`
- `APA 7th Edition`
- `Vancouver Style`
- `IEEE Citation Style`

### 3.2 Construção e resolução do prompt

```php
$agent = new BibliografiaAgent($standard, $version);
```

- `$standard`: norma bibliográfica que será interpolada no template do prompt (`{$standard}` / `{{standard}}`).
- `$version` *(opcional)*: permite testar uma versão específica (ex.: `draft`) cadastrada na tabela `ai_prompts`. Quando omitido (`null`), utiliza a versão `active` em produção (ou `BibliografiaAgent::defaultTemplate()` como fallback).

---

## 4. Schema estruturado e validação de correspondência

### 4.1 Schema das quatro listas

`BibliografiaAgent::schema()` define quatro listas obrigatórias na raiz do JSON:

| Campo | Tipo e restrições | Uso |
| --- | --- | --- |
| `referencias` | Lista obrigatória de strings (`array<string>`). | Referências corrigidas e formatadas na norma selecionada. |
| `explicacoes` | Lista obrigatória de strings (`array<string>`). | Ajustes específicos realizados ou elementos ausentes em cada referência. |
| `busca_dedalus` | Lista obrigatória de objetos com `autor` e `titulo` (`string`). | Dados limpos preparados para consulta ao catálogo Dedalus. |
| `confiancas` | Lista obrigatória de inteiros entre `0` e `100`. | Qualidade e completude da referência original enviada pelo usuário. |

Exemplo de resposta estruturada devolvida pelo agente:

```json
{
  "referencias": ["Referência revisada A."],
  "explicacoes": ["Pontuação revisada."],
  "busca_dedalus": [{"autor": "Autor A", "titulo": "Título A"}],
  "confiancas": [90]
}
```

### 4.2 Validação de correspondência (`validateCorrespondence()`)

Como o JSON Schema valida cada lista isoladamente mas não compara o tamanho entre listas distintas, `BibliografiaAgent` sobrescreve `prompt()` para executar `validateCorrespondence()` logo após receber a `StructuredAgentResponse`:

- Confere que `referencias`, `explicacoes`, `busca_dedalus` e `confiancas` são listas válidas (`array_is_list()`).
- Exige que todas as quatro listas tenham **exatamente a mesma quantidade de itens** (associação 1-para-1 pelo índice). Caso contrário, lança `RuntimeException`, que é tratada pelo controller como resposta inválida da IA (HTTP 502).

---

## 5. Integração com o acervo Dedalus (`DedalusService`)

O serviço [app/Services/DedalusService.php](../app/Services/DedalusService.php) consulta a API pública de busca do ABCD/USP (`WSearch.php`):

- **Configuração (`.env` e `config/dedalus.php`)**:
  - `DEDALUS_URL`: URL base da busca (padrão: `https://app.abcd.usp.br/WSearch.php`).
  - `DEDALUS_LIBRARY`: sigla da biblioteca padrão (padrão: `EESC`).
  - `DEDALUS_TIMEOUT`: tempo limite em segundos para aguardar resposta da API (padrão: `30`).
- **Construção do termo (`buildSearchQuery()`)**:
  - Prioriza o título principal de `titulo` (antes de `:` ou `;`, compatível com `options=byTitle`), recorrendo ao sobrenome/segmento principal de `autor` apenas quando o título estiver ausente, e ignorando marcadores vazios (`N/A`, `[s.n.]`).
- **Parâmetros enviados ao `WSearch.php`**:
  - `search`, `library`, `resourceType=books` e `options=byTitle`.
- **Execução e resiliência (`search()` e `searchBatch()`)**:
  - Armazena respostas válidas em cache respeitando `config('aiService.cacheRetention')`.
  - Caso o endpoint do Dedalus esteja indisponível ou retorne erro HTTP, registra um aviso em log e devolve `publications: []` com a mensagem em `error`, sem interromper a exibição das referências corrigidas pela IA.

---

## 6. Contrato público (Web e JSON)

Em caso de sucesso, o controller fornece os seguintes dados para a view `bibliografia.index` e para o envelope JSON `{"success": true, "data": {...}}`:

| Campo em `data` | Origem |
| --- | --- |
| `referencias` | Texto original enviado pelo usuário no campo `message`. |
| `respostas` | Lista `referencias` retornada pelo agente (referências corrigidas). |
| `explicacoes` | Lista `explicacoes` retornada pelo agente. |
| `confiancas` | Lista `confiancas` retornada pelo agente (`0` a `100`). |
| `buscaDedalus` | Lista original de pares `{autor, titulo}` retornada pelo agente em `busca_dedalus`. |
| `dedalus` | Lista de resultados retornada por `DedalusService::searchBatch()` (`query`, `library`, `url`, `total`, `publications`, `error`). |
| `estatisticas` | Metadados da execução retornados por `AiService::statistics()`. |
| `prompt` | Texto final do prompt de sistema utilizado na execução. |
| `standards` | Lista de normas disponíveis (`BibliografiaAgent::standards()`). |
| `standard` | Norma selecionada na requisição. |

---

## 7. Apresentação na interface web

A tela [resources/views/bibliografia/index.blade.php](../resources/views/bibliografia/index.blade.php) exibe os resultados em três colunas sincronizadas:

1. **Alterações (`#referencias-diff`)**: mostra visualmente as adições e remoções em relação ao texto original usando `UIDiffViewer`.
2. **Referências corrigidas (`#resposta`)**: painel `contenteditable="true"` contendo cada referência com `data-index="{{ $index }}"` e botão de cópia (`bibliografia.partials.copiar-btn`). Editar o texto recalcula o diff automaticamente.
3. **Explicações e qualidade da entrada (`#explicacao`)**: exibe a explicação de cada item acompanhada de:
   - Um badge colorido conforme o índice de confiança (`>= 89%` verde, `>= 64%` amarelo, `< 64%` vermelho).
   - Um botão/badge `Dedalus (<biblioteca>): <total>` que abre o modal de resultados do Dedalus (`#modal-dedalus-{{ $index }}`), onde o usuário visualiza as publicações encontradas (`title`, `authors`, `editor`, `publicationDate`, `format`, `mediaType`, `sysNo`, `subjects`) e pode editar o termo de busca ou a unidade para pesquisar novamente sem sair da tela.

Passar o mouse sobre uma referência ou explicação destaca o par correspondente (`UICrossHighlight`).

---

## 8. Testes do módulo

Para executar os testes específicos do agente, do serviço Dedalus e do fluxo de bibliografia:

```bash
vendor/bin/phpunit tests/Feature/BibliografiaAgentTest.php
vendor/bin/phpunit tests/Feature/DedalusServiceTest.php
vendor/bin/phpunit --filter bibliography tests/Feature/AiControllerTest.php
```

