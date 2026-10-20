# USP AI Services (Portal & API)

<a href="https://github.com/uspdev"><img src="docs/uspdev-horizontal.png" height="20" alt="USPdev" align="middle"></a>
[![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net/)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)

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
| `AI_PROMPT_LOG_RETENTION_MONTHS` | Período (em meses) dos logs de auditoria de prompts exibidos na interface administrativa (padrão: `12`). |
| `DEDALUS_URL` | Endpoint da busca do catálogo Dedalus/ABCD da USP. |
| `DEDALUS_LIBRARY` | Sigla da biblioteca padrão para consulta no Dedalus (ex.: `EESC`). |
| `DEDALUS_TIMEOUT` | Timeout em segundos para requisições ao Dedalus (padrão: `30`). |

`config/ai.php` reúne provider, driver, URL, chave e modelo. A configuração do projeto acrescenta `/v1` para manter o endpoint `/v1/chat/completions` já utilizado; bases com `/v1` também são aceitas sem duplicação. Esse prefixo é uma escolha desta integração, não uma exigência do driver do SDK.

`config/aiService.php` mantém a retenção do cache (`AI_CACHE_RETENTION`) e o período dos logs (`AI_PROMPT_LOG_RETENTION_MONTHS`). O armazenamento do cache é escolhido por `config/cache.php`, que neste projeto lê `CACHE_DRIVER`. O cache só guarda respostas validadas, separadas por agente, prompt, schema, provider, configuração, modelo e entrada.

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
