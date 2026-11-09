# Serviço de Revisão de Ementas e Conteúdo Pedagógico

Este documento detalha o funcionamento, os contratos de dados, as validações e a interface do serviço de revisão de ementas. Para a arquitetura base (configuração do SDK, LiteLLM, `AiService`, cache, versionamento de prompts e tratamento de falhas), consulte a [Arquitetura Geral](implementacao-laravel-ai-sdk.md).

## Índice

- [1. Visão geral e arquivos do módulo](#1-visão-geral-e-arquivos-do-módulo)
- [2. Rota, controller e validação de entrada](#2-rota-controller-e-validação-de-entrada)
- [3. Agente de ementa (`EmentaAgent`)](#3-agente-de-ementa-ementaagent)
- [4. Schema estruturado bilíngue](#4-schema-estruturado-bilíngue)
- [5. Contrato público (Web e JSON)](#5-contrato-público-web-e-json)
- [6. Apresentação na interface web](#6-apresentação-na-interface-web)
- [7. Testes do módulo](#7-testes-do-módulo)

---

## 1. Visão geral e arquivos do módulo

O módulo de revisão de ementas avalia e padroniza textos pedagógicos (ementa, objetivos e conteúdo programático) em português e inglês segundo as diretrizes acadêmicas da USP. Ele atribui uma nota de qualidade (`0` a `100`), apresenta diagnósticos separados por idioma e categoria, verifica a presença/equivalência da versão em inglês e produz versões revisadas (ou traduzidas, quando o inglês estiver ausente) para as três seções.

| Arquivo | Responsabilidade |
| --- | --- |
| [app/Ai/Agents/EmentaAgent.php](../app/Ai/Agents/EmentaAgent.php) | Define `SLUG = 'ementa'`, template padrão (`defaultTemplate()`) e schema estruturado bilíngue. |
| [app/Http/Controllers/EmentaController.php](../app/Http/Controllers/EmentaController.php) | Atende `GET/POST /ementa` (`ementa.index`), valida `mensagem`, executa o agente no `AiService` e retorna a view ou JSON. |
| [resources/views/ementa/index.blade.php](../resources/views/ementa/index.blade.php) | Exibe a nota, as explicações bilíngues, o comparador de alterações (diff) e o texto revisado das três seções. |
| [resources/views/ementa/partials/form.blade.php](../resources/views/ementa/partials/form.blade.php) | Formulário de envio para `route('ementa.index')`. |
| [resources/views/ementa/partials/ingles-presente-badge.blade.php](../resources/views/ementa/partials/ingles-presente-badge.blade.php) | Badge que sinaliza o status do texto em inglês (`presente`, `parcial` ou `ausente`). |
| [tests/Feature/EmentaAgentTest.php](../tests/Feature/EmentaAgentTest.php) | Verifica resolução do prompt, schema aninhado e os estados do inglês. |

---

## 2. Rota, controller e validação de entrada

A rota está registrada em [routes/web.php](../routes/web.php) sob o middleware `auth`:

| Endpoint | Nome da rota | Controller | Campos obrigatórios no POST |
| --- | --- | --- | --- |
| `/ementa` | `ementa.index` | `EmentaController@index` | `mensagem`: string de até 10.000 caracteres. |

- **`GET /ementa`**:
  - Na web: renderiza `ementa.index` com o formulário.
  - No JSON (`Accept: application/json`): retorna HTTP 200 com a instrução `Envie uma requisição POST com o campo "mensagem" para processar.`.
- **`POST /ementa`**:
  - Valida `mensagem` antes de acionar a IA (retornando HTTP 422 no JSON caso inválido).
  - Instancia `new EmentaAgent` e envia `$mensagem` ao `AiService::submit()`.

---

## 3. Agente de ementa (`EmentaAgent`)

```php
$agent = new EmentaAgent($version);
```

- `$version` *(opcional)*: permite testar uma versão específica (ex.: `draft`) cadastrada na tabela `ai_prompts` para o slug `'ementa'`. Quando omitido (`null`), utiliza a versão `active` em produção (ou `EmentaAgent::defaultTemplate()` como fallback).

---

## 4. Schema estruturado bilíngue

`EmentaAgent::schema()` exige três objetos na raiz do JSON (`score`, `explicacoes` e `texto_revisado`):

| Caminho | Tipo e restrições | Descrição |
| --- | --- | --- |
| `score.valor` | Inteiro obrigatório de `0` a `100`. | Nota atribuída à qualidade e completude do texto original. |
| `score.justificativa` | String obrigatória. | Resumo executivo de 1 a 2 frases justificando a nota. |
| `explicacoes.pt` | Objeto obrigatório com 3 strings. | Análises em português (`gramatica_acentuacao_pontuacao`, `redacao_academica_clareza_pedagogica`, `terminologia_tecnica`). |
| `explicacoes.en` | Objeto obrigatório com `status` + 3 strings. | Análises em inglês e estado do inglês na entrada original. |
| `explicacoes.en.status` | Enum obrigatório: `presente`, `parcial` ou `ausente`. | Indica se o texto original continha versão completa, parcial ou ausente em inglês. |
| `texto_revisado.ementa` | Objeto obrigatório com strings `pt` e `en`. | Versão final revisada da Ementa / Course Description. |
| `texto_revisado.objetivos` | Objeto obrigatório com strings `pt` e `en`. | Versão final revisada dos Objetivos / Objectives. |
| `texto_revisado.conteudo_programatico` | Objeto obrigatório com strings `pt` e `en`. | Versão final revisada do Conteúdo Programático / Full Program. |

Exemplo de resposta estruturada devolvida pelo agente:

```json
{
  "score": {
    "valor": 90,
    "justificativa": "Texto claro, com ajustes pontuais de pontuação."
  },
  "explicacoes": {
    "pt": {
      "gramatica_acentuacao_pontuacao": "Pontuação revisada.",
      "redacao_academica_clareza_pedagogica": "Objetivos apresentados com clareza.",
      "terminologia_tecnica": "Terminologia consistente."
    },
    "en": {
      "status": "ausente",
      "gramatica_acentuacao_pontuacao": "Tradução criada a partir do texto em português.",
      "redacao_academica_clareza_pedagogica": "Texto organizado em inglês acadêmico.",
      "terminologia_tecnica": "Terminologia técnica traduzida."
    }
  },
  "texto_revisado": {
    "ementa": {
      "pt": "Introdução à programação.",
      "en": "Introduction to programming."
    },
    "objetivos": {
      "pt": "Desenvolver algoritmos.",
      "en": "Develop algorithms."
    },
    "conteudo_programatico": {
      "pt": "Variáveis e funções.",
      "en": "Variables and functions."
    }
  }
}
```

Mesmo quando `explicacoes.en.status` é `ausente`, os campos `en` de `texto_revisado` são obrigatórios e preenchidos com a tradução técnica elaborada pelo modelo.

---

## 5. Contrato público (Web e JSON)

Em caso de sucesso, `EmentaController` fornece os seguintes dados para a view `ementa.index` e para o envelope JSON `{"success": true, "data": {...}}`:

| Campo em `data` | Origem |
| --- | --- |
| `mensagem` | Texto original enviado pelo usuário no campo `mensagem`. |
| `resposta` | Objeto `texto_revisado` do resultado do agente (contendo `ementa`, `objetivos` e `conteudo_programatico` em `pt` e `en`). |
| `explicacoes` | Objeto `explicacoes` do resultado (`pt` e `en`). |
| `analiseIngles` | Atalho para `explicacoes.en` (inclui `status` e as três análises em inglês). |
| `score` | Objeto `score` (`valor` e `justificativa`). |
| `estatisticas` | Metadados da execução retornados por `AiService::statistics()`. |
| `prompt` | Texto final do prompt de sistema utilizado na execução. |

---

## 6. Apresentação na interface web

A tela [resources/views/ementa/index.blade.php](../resources/views/ementa/index.blade.php) organiza a apresentação em duas seções:

1. **Painel superior — Explicações e qualidade da entrada (`#explicacao`)**:
   - Exibe `Score: {{ $score['valor'] }}/100` e `{{ $score['justificativa'] }}`.
   - Exibe o indicador de idioma com `ementa.partials.ingles-presente-badge` (`Status: Presente`, `Parcial` ou `Ausente`).
   - Lista os três tópicos de avaliação (**Gramática, Acentuação e Pontuação**, **Redação Acadêmica e Clareza Pedagógica** e **Terminologia Técnica e Vocabulário**), apresentando lado a lado os diagnósticos `PT` e `EN`.
2. **Painel inferior — Alterações e Texto corrigido**:
   - **Alterações (`#referencias-diff`)**: comparador visual entre a entrada original e o texto revisado (`UIDiffViewer`).
   - **Texto corrigido (`#resposta`)**: painel `contenteditable="true"` dividido em **Ementa**, **Objetivos** e **Conteúdo Programático** (com texto em português e versão em inglês em itálico logo abaixo), acompanhado do botão `ementa.partials.copiar-btn`.
   - Os seis campos de texto revisado são exibidos com `{!! nl2br(e(...)) !!}`, escapando qualquer HTML e preservando as quebras de linha.

---

## 7. Testes do módulo

Para executar os testes específicos do agente e do fluxo de ementa:

```bash
vendor/bin/phpunit tests/Feature/EmentaAgentTest.php
vendor/bin/phpunit --filter syllabus tests/Feature/AiControllerTest.php
```

