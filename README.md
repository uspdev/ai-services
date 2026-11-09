# USP AI Services (Portal & API)

<a href="https://github.com/uspdev"><img src="docs/uspdev-horizontal.png" height="20" alt="USPdev" align="middle"></a>
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](https://opensource.org/license/mit)

Plataforma centralizada de serviços baseados em Inteligência Artificial. O sistema oferece tanto uma **interface web (playground)** para uso direto quanto uma **API RESTful** para integração com sistemas.

---

## 🛠️ Serviços de IA

### 1. Formatação e Normalização Bibliográfica
Ajuste e padronização automática de referências bibliográficas de acordo com normas acadêmicas (ABNT NBR 6023, APA, etc.), com tratamento inteligente de ruídos, autores, intervalos de páginas e consulta integrada ao acervo Dedalus/ABCD da USP via AJAX.

### 2. Revisão de Ementas
Revisão de ementa, objetivos e conteúdo programático em português e inglês, com avaliação de qualidade, explicações e tradução dos trechos cujo inglês estiver ausente.

### 3. Elaboração de Textos a partir de Rascunhos
Transformação de rascunhos, tópicos ou anotações em textos institucionais ou acadêmicos claros, coesos e bem estruturados (comunicados, ofícios, despachos, pareceres, e-mails ou modo automático com inferência inteligente de estilo e sugestão de assunto).

---

## ✨ Recursos do Sistema

### Gerenciamento e Versionamento de Prompts de IA
Painel web administrativo (`/settings/prompts`) restrito a gerentes (`can:manager`) para controle das instruções de sistema (`system_prompt`), metadados e ciclo de vida de versões (`draft`, `active`, `archived`) com comparador visual Diff em tempo real, restauração de padrões de código e histórico de auditoria em `storage/logs/aiprompt.log`.

---

## 💻 Instalação e Configuração Local

### Pré-requisitos

* PHP 8.3 ou superior
* Composer
* Laravel 13
* Banco de Dados (MySQL / PostgreSQL / SQLite)
* Gateway LiteLLM com modelo que aceite saída estruturada via `json_schema`

### Passo a Passo

```bash
git clone git@github.com:uspdev/ai-services ai-services
cd ai-services
composer install
cp .env.example .env
php artisan key:generate
```

### Senha única

Cadastre a URL de callback no configurador de Senha Única com o caminho `/callback`. Guarde o `callback_id` para preencher `SENHAUNICA_CALLBACK_ID` no `.env` da aplicação.

### Configuração de IA e Serviços

Preencha as variáveis no `.env` com os valores do seu gateway e integrações:

```dotenv
AI_PROVIDER=litellm
LITELLM_URL=http://localhost:4000
LITELLM_API_KEY=
LITELLM_MODEL=gemini-flash
AI_CACHE_RETENTION=604800
AI_CONVERSATION_LOG_ENABLED=${APP_DEBUG}
AI_PROMPT_LOG_RETENTION_MONTHS=12

DEDALUS_URL=https://app.abcd.usp.br/WSearch.php
DEDALUS_LIBRARY=EESC
DEDALUS_TIMEOUT=30
```

| Variável | Uso |
| --- | --- |
| `AI_PROVIDER` | Nome do provider em `config/ai.php`; o padrão é `litellm`. |
| `LITELLM_URL` | URL base do gateway, sem `/chat/completions`. |
| `LITELLM_API_KEY` | Chave de acesso ao gateway. |
| `LITELLM_MODEL` | Alias de modelo exposto pelo LiteLLM; `gemini-flash` é o exemplo do `.env.example`. |
| `AI_CACHE_RETENTION` | Retenção das respostas validadas em segundos; padrão de sete dias (`604800`). Zero desabilita o cache. |
| `AI_CONVERSATION_LOG_ENABLED` | Ativa o registro completo das chamadas em `storage/logs/ai-conversations.log`; no exemplo acompanha `APP_DEBUG`. |
| `AI_PROMPT_LOG_RETENTION_MONTHS` | Período (em meses) dos logs de auditoria de prompts exibidos na interface administrativa (padrão: `12`). |
| `DEDALUS_URL` | Endpoint da busca do catálogo Dedalus/ABCD da USP. |
| `DEDALUS_LIBRARY` | Sigla da biblioteca padrão para consulta no Dedalus (ex.: `EESC`). |
| `DEDALUS_TIMEOUT` | Timeout em segundos para requisições ao Dedalus (padrão: `30`). |

`config/ai.php` reúne provider, driver, URL, chave e modelo. A configuração do projeto acrescenta `/v1` para manter o endpoint `/v1/chat/completions` já utilizado; bases com `/v1` também são aceitas sem duplicação. Esse prefixo é uma escolha desta integração, não uma exigência do driver do SDK.

`config/aiService.php` mantém a retenção do cache (`AI_CACHE_RETENTION`) e o período dos logs (`AI_PROMPT_LOG_RETENTION_MONTHS`). O armazenamento do cache é escolhido por `config/cache.php`, que neste projeto lê `CACHE_DRIVER`. O cache só guarda respostas validadas, separadas por agente, prompt, schema, provider, configuração, modelo e entrada.

### Agentes e saída estruturada

Os três agentes usam `#[Strict]` para exigir o JSON Schema no provider. Bibliografia e ementa mantêm seus prompts simplificados, sem exemplos de serialização JSON; os templates padrão ficam nos próprios agentes e o banco permite editar e ativar versões. As regras bibliográficas, a correspondência das listas, a avaliação acadêmica e a tradução permanecem nas instruções. Versões já salvas no banco são preservadas.

### Sistema de logs


Os logs gerais ficam em `storage/logs/laravel.log`; a captura das chamadas de IA usa `storage/logs/ai-conversations.log`, e a integração com o Replicado usa `storage/logs/replicado.log`. O visualizador está disponível em `/laravel-tools/logs` para usuários autorizados pelo Gate `admin`.

Consulte a [documentação da arquitetura](docs/implementacao-laravel-ai-sdk.md#81-registro-das-chamadas-de-ia) para entender a captura das chamadas. O histórico de alterações dos prompts fica em `storage/logs/aiprompt.log`.

### Log de pedidos e retornos da IA

Para inspecionar as chamadas, configure no `.env`:

```dotenv
AI_CONVERSATION_LOG_ENABLED=true
# Use zero para testar novas respostas em toda solicitação, sem reutilizar o cache.
AI_CACHE_RETENTION=0
```

Depois de alterar a configuração, execute `php artisan config:clear`. Faça uma solicitação de bibliografia, ementa ou elaboração de textos e acompanhe o arquivo:

```bash
tail -f storage/logs/ai-conversations.log
```

O canal dedicado mantém o cabeçalho do Laravel, com data, nível e evento. A identificação da execução aparece primeiro, seguida de blocos indentados para mensagens, payload, resposta e estatísticas. Prompts e textos usam quebras de linha reais; schemas usam JSON com indentação. Cada execução recebe um `request_id`, compartilhado pelos seus registros:

| Evento | Conteúdo |
| --- | --- |
| `ai.request` | Agente, provider, modelo, instruções de sistema, entrada do usuário e schema solicitado pela aplicação. |
| `ai.http.request` | Método, URL e payload HTTP real montado pelo SDK, incluindo `messages` e `response_format`. |
| `ai.http.response` | Status e corpo bruto recebidos, antes de interpretar o JSON e conferir o término da geração. |
| `ai.result` | Resultado recebido pelo serviço e estatísticas da geração. |
| `ai.cache_hit` | Resultado e estatísticas reaproveitados, sem nova comunicação com a IA. |
| `ai.http.connection_failed` | Falha de conexão com o provider. |
| `ai.error` | Classe e mensagem interna da falha de execução ou validação. |

Respostas inválidas ou incompletas também ficam registradas. JSON contido em `body` ou `content` é expandido em campos somente para exibição, evitando camadas de aspas escapadas; conteúdo que não seja JSON continua como texto. O formatter normaliza os finais de linha para leitura, preservando sequências literais como `\n` e caminhos com barras. Cabeçalhos de autenticação e configuração de credenciais não são registrados; o arquivo contém os textos completos enviados pelo usuário e recebidos do provider. Sem a variável de ambiente, a captura fica desativada; no `.env.example`, `AI_CONVERSATION_LOG_ENABLED=${APP_DEBUG}` faz a captura acompanhar `APP_DEBUG`. A captura cobre chamadas feitas por `AiService::submit()`; chamadas diretas a `$agent->prompt()` não entram nesse log. Para encerrar os testes, defina `AI_CONVERSATION_LOG_ENABLED=false` e restaure a retenção desejada do cache. O novo formato vale para registros produzidos após a alteração; registros antigos não são reescritos.

Os testes automatizados simulam HTTP e verificam a integração e o arquivo de log. A qualidade das respostas reais após simplificar os prompts deve ser avaliada com o gateway e o modelo configurados.

### Banco de Dados e Migrations

Execute as migrations para criar as tabelas da aplicação (incluindo `ai_prompts`):

```bash
php artisan migrate
```

Opcionalmente, popule de forma idempotente a versão inicial (`v1`) dos prompts padrão no banco (caso contrário, eles são inicializados automaticamente no primeiro acesso a `/settings/prompts` ou executados via fallback em código):

```bash
php artisan db:seed --class=AiPromptSeeder
```

---

## 📚 Documentação Técnica

- **[Guia da API e Agentes](docs/api.md)**: especificação dos endpoints JSON, autenticação, saídas estruturadas dos agentes, envelopes de resposta e tratamento de erros.
- **[Arquitetura Geral e Integração com o Laravel AI SDK](docs/implementacao-laravel-ai-sdk.md)**: configuração do LiteLLM, versionamento e CRUD de prompts (`AiPrompt`), `AiService`, cache, estatísticas, tratamento de falhas (`HandlesAiFailures`) e testes.
- **[Serviço de Bibliografia](docs/bibliografia.md)**: detalhes do `BibliografiaController`, `BibliografiaAgent`, normas suportadas, consulta assíncrona ao Dedalus (`DedalusService`) e interface.
- **[Serviço de Ementa](docs/ementa.md)**: detalhes do `EmentaController`, `EmentaAgent`, schema bilíngue e interface.
- **[Serviço de Elaboração de Texto](docs/texto.md)**: detalhes do `TextoController`, `TextoAgent`, detecção automática de estilos, sugestão de assunto e interface.
