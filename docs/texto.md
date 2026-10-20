# Serviço de Elaboração e Aperfeiçoamento de Textos

Este documento detalha o funcionamento, os contratos de dados, os estilos disponíveis e a interface do serviço de elaboração de textos a partir de rascunhos do usuário. Para a arquitetura base (configuração do SDK, LiteLLM, `AiService`, cache, versionamento de prompts e tratamento de falhas), consulte a [Arquitetura Geral](implementacao-laravel-ai-sdk.md).

## Índice

- [1. Visão geral e arquivos do módulo](#1-visão-geral-e-arquivos-do-módulo)
- [2. Rota, controller e validação de entrada](#2-rota-controller-e-validação-de-entrada)
- [3. Agente de elaboração de texto (`TextoAgent`)](#3-agente-de-elaboração-de-texto-textoagent)
- [4. Schema estruturado da resposta](#4-schema-estruturado-da-resposta)
- [5. Contrato público (Web e JSON)](#5-contrato-público-web-e-json)
- [6. Apresentação na interface web](#6-apresentação-na-interface-web)
- [7. Testes do módulo](#7-testes-do-módulo)

---

## 1. Visão geral e arquivos do módulo

O módulo de elaboração de texto recebe um rascunho ou lista de tópicos do usuário, um estilo/formato textual e opcionalmente um contexto/destinatário, devolvendo o texto elaborado e revisado, uma sugestão de assunto/título, o resumo das melhorias aplicadas e eventuais observações de preenchimento.

| Arquivo | Responsabilidade |
| --- | --- |
| [app/Ai/Agents/TextoAgent.php](../app/Ai/Agents/TextoAgent.php) | Define `SLUG = 'texto'`, estilos suportados (`styles()`), template padrão (`defaultTemplate()`) e o schema estruturado da resposta. |
| [app/Http/Controllers/TextoController.php](../app/Http/Controllers/TextoController.php) | Atende `GET/POST /texto` (`texto.index`), valida `rascunho`, `style` e `contexto`, executa o agente no `AiService` e retorna a view ou JSON. |
| [resources/views/texto/index.blade.php](../resources/views/texto/index.blade.php) | Exibe o formulário, o assunto sugerido, o comparador de alterações (diff), o texto elaborado editável e a lista de melhorias e observações. |
| [resources/views/texto/partials/form.blade.php](../resources/views/texto/partials/form.blade.php) | Formulário de envio para `route('texto.index')` com campos `rascunho`, `style` e `contexto`. |
| [resources/views/texto/partials/copiar-btn.blade.php](../resources/views/texto/partials/copiar-btn.blade.php) | Botão para copiar o texto elaborado (incluindo edições manuais feitas na tela). |
| [tests/Feature/TextoAgentTest.php](../tests/Feature/TextoAgentTest.php) | Verifica a interpolação dos estilos textuais e o envio do schema JSON estruturado. |

---

## 2. Rota, controller e validação de entrada

A rota está registrada em [routes/web.php](../routes/web.php) sob o middleware `auth`:

| Endpoint | Nome da rota | Controller | Campos no POST |
| --- | --- | --- | --- |
| `GET/POST /texto` | `texto.index` | `TextoController@index` | `rascunho`: string obrigatória de até 10.000 caracteres;<br/>`style`: obrigatório, um dos valores de `TextoAgent::styles()`;<br/>`contexto`: string opcional de até 1.000 caracteres. |

- **`GET /texto`**:
  - Na web: renderiza `texto.index` com a lista de estilos disponíveis (`$styles`).
  - No JSON (`Accept: application/json`): retorna HTTP 200 com a instrução `Envie uma requisição POST com os campos "rascunho" e "style" para processar.`.
- **`POST /texto`**:
  - Valida `rascunho`, `style` e `contexto` antes de acionar a IA (retornando HTTP 422 no JSON caso inválido).
  - Quando `contexto` é informado, antepõe `Contexto/Objetivo: <contexto>` ao rascunho enviado para `AiService::submit()`.

---

## 3. Agente de elaboração de texto (`TextoAgent`)

### 3.1 Estilos textuais (`styles()`)

`TextoAgent::styles()` consulta primeiro `metadata['styles']` da versão ativa do prompt `'texto'` na tabela `ai_prompts`. Caso não haja customização no banco de dados, utiliza a constante `TextoAgent::DEFAULT_STYLES`:

- `Automático (detectar pelo rascunho)`
- `Comunicado / E-mail Institucional`
- `Ofício / Despacho Administrativo`
- `Parecer / Relatório Técnico`
- `Texto Acadêmico / Científico`
- `Texto Claro e Conciso (Geral)`

### 3.2 Construção e resolução do prompt

```php
$agent = new TextoAgent($style, $version);
```

- `$style`: estilo/gênero textual interpolado no template do prompt (`{$style}` / `{{style}}`).
- `$version` *(opcional)*: permite testar uma versão específica (ex.: `draft`) cadastrada na tabela `ai_prompts`.

---

## 4. Schema estruturado da resposta

`TextoAgent::schema()` define cinco campos obrigatórios na raiz do JSON:

| Campo | Tipo | Descrição |
| --- | --- | --- |
| `texto_elaborado` | `string` | Texto completo elaborado e estruturado em português, sem marcação Markdown ou HTML. |
| `estilo_aplicado` | `string` | Estilo/formato efetivamente adotado pela IA (especialmente útil quando `Automático (detectar pelo rascunho)` é selecionado). |
| `assunto_sugerido` | `string` | Sugestão concisa de linha de assunto ou título para o documento. |
| `melhorias` | `array<string>` | Lista das melhorias editoriais, estruturais e gramaticais aplicadas ao rascunho. |
| `observacoes` | `string` | Alerta sobre lacunas ou marcadores entre colchetes (ex.: `[informar data]`) que o usuário deve preencher, ou string vazia se completo. |

---

## 5. Contrato público (Web e JSON)

Em caso de sucesso, o controller fornece os seguintes dados para a view `texto.index` e para o envelope JSON `{"success": true, "data": {...}}`:

| Campo em `data` | Origem |
| --- | --- |
| `rascunho` | Rascunho original enviado pelo usuário. |
| `contexto` | Contexto/destinatário opcional informado na requisição. |
| `style` | Estilo selecionado na requisição. |
| `styles` | Lista de estilos disponíveis (`TextoAgent::styles()`). |
| `textoElaborado` | Campo `texto_elaborado` retornado pelo agente. |
| `estiloAplicado` | Campo `estilo_aplicado` retornado pelo agente. |
| `assuntoSugerido` | Campo `assunto_sugerido` retornado pelo agente. |
| `melhorias` | Lista `melhorias` retornada pelo agente. |
| `observacoes` | Campo `observacoes` retornado pelo agente. |
| `estatisticas` | Metadados da execução retornados por `AiService::statistics()`. |
| `prompt` | Texto final do prompt de sistema utilizado na execução. |

---

## 6. Apresentação na interface web

A tela [resources/views/texto/index.blade.php](../resources/views/texto/index.blade.php) apresenta:

- **Assunto / Título sugerido**: exibido em destaque acima das colunas de resultado.
- **Três colunas sincronizadas (`UIHeightSync`)**:
  1. **Alterações (`#texto-diff`)**: comparação visual entre o rascunho original e o texto elaborado (`UIDiffViewer`).
  2. **Texto elaborado (`#resposta`)**: área editável (`contenteditable="true"`) com contador de parágrafos e botão de cópia (`texto.partials.copiar-btn`).
  3. **Melhorias aplicadas e observações (`#explicacao`)**: alerta amarelo para eventuais `observacoes` de preenchimento e lista de `melhorias` aplicadas.

---

## 7. Testes do módulo

Para executar os testes específicos do agente e do fluxo de elaboração de texto:

```bash
vendor/bin/phpunit tests/Feature/TextoAgentTest.php
vendor/bin/phpunit --filter text tests/Feature/AiControllerTest.php
```

