# Especificação da API e Agentes de IA

Este documento descreve os endpoints RESTful/JSON da plataforma USP AI Services, a autenticação, os contratos de entrada e saída estruturada dos agentes de IA e as instruções para consumo via código PHP ou integração externa.

---

## Índice

- [1. Visão Geral e Autenticação](#1-visão-geral-e-autenticação)
- [2. Endpoints da API](#2-endpoints-da-api)
  - [2.1 Formatação Bibliográfica (`/bibliografia`)](#21-formatação-bibliográfica-bibliografia)
  - [2.2 Busca no Dedalus (`/bibliografia/dedalus`)](#22-busca-no-dedalus-bibliografiadedalus)
  - [2.3 Revisão de Ementas (`/ementa`)](#23-revisão-de-ementas-ementa)
  - [2.4 Elaboração de Textos (`/texto`)](#24-elaboração-de-textos-texto)
- [3. Agentes de IA e Uso em Código PHP](#3-agentes-de-ia-e-uso-em-código-php)
  - [3.1 BibliografiaAgent](#31-bibliografiaagent)
  - [3.2 EmentaAgent](#32-ementaagent)
  - [3.3 TextoAgent](#33-textoagent)
  - [3.4 Exemplo de Invocação via AiService](#34-exemplo-de-invocação-via-aiservice)
  - [3.5 Estatísticas de Execução e Cache](#35-estatísticas-de-execução-e-cache)
- [4. Tratamento de Erros e Códigos HTTP](#4-tratamento-de-erros-e-códigos-http)

---

## 1. Visão Geral e Autenticação

Todos os serviços de IA oferecem tanto interface web interativa quanto respostas estruturadas em JSON. Para obter a resposta em formato JSON em qualquer rota, inclua o cabeçalho HTTP:

```http
Accept: application/json
```

### Autenticação e Proteção CSRF

- As rotas residem sob o grupo de middleware `auth` em `routes/web.php`.
- **Requisições de navegador/SPA**: Reutilizam a sessão autenticada (via Senha Única USP). Em requisições `POST`, é obrigatório enviar o token CSRF (`X-CSRF-TOKEN` no cabeçalho ou `_token` no corpo).
- **Limite de Entrada**: Todas as entradas textuais dos serviços possuem validação com limite de até **10.000 caracteres** (e 2.000 caracteres para campos contextuais adicionais).

---

## 2. Endpoints da API

### 2.1 Formatação Bibliográfica (`/bibliografia`)

Normaliza e ajusta referências de acordo com a norma acadêmica especificada, extraindo termos para busca no catálogo Dedalus/USP e notas explicativas.

- **Método**: `GET` (informa os parâmetros aceitos quando `Accept: application/json`) ou `POST` (processamento).
- **Parâmetros no POST**:
  - `message` (*string*, obrigatório, máx. 10.000 caracteres): uma ou mais referências a serem formatadas.
  - `standard` (*string*, obrigatório): norma desejada. Valores válidos disponíveis em `BibliografiaAgent::standards()`:
    - `ABNT NBR 6023:2025`
    - `APA 7th edition`
    - `Vancouver`
    - `NLM`
    - `ISO 690:2021`

#### Exemplo de Requisição

```http
POST /bibliografia HTTP/1.1
Host: localhost:8000
Accept: application/json
Content-Type: application/json
X-CSRF-TOKEN: <token>

{
  "standard": "ABNT NBR 6023:2025",
  "message": "SILVA, J. Inteligência artificial na educação. São Paulo: USP, 2024."
}
```

#### Exemplo de Resposta (Sucesso - 200 OK)

```json
{
  "success": true,
  "data": {
    "respostas": [
      "SILVA, J. Inteligência artificial na educação. São Paulo: USP, 2024."
    ],
    "explicacoes": [
      "Referência ajustada conforme a norma ABNT NBR 6023:2025."
    ],
    "confiancas": [
      "alta"
    ],
    "buscaDedalus": [
      "Inteligência artificial na educação SILVA"
    ],
    "entrada": "SILVA, J. Inteligência artificial na educação. São Paulo: USP, 2024.",
    "norma": "ABNT NBR 6023:2025",
    "estatisticas": {
      "prompt_tokens": 420,
      "completion_tokens": 85,
      "total_tokens": 505,
      "provider": "litellm",
      "model": "gemini-flash",
      "cached": false
    }
  }
}
```

---

### 2.2 Busca no Dedalus (`/bibliografia/dedalus`)

Consulta assíncrona ao catálogo Dedalus/ABCD da USP para localização de exemplares no acervo físico ou digital.

- **Método**: `GET`
- **Query Parameters**:
  - `search` (*string*, obrigatório): termo de busca gerado pelo `BibliografiaAgent` ou digitado pelo usuário.
  - `library` (*string*, opcional): sigla da biblioteca USP (ex.: `EESC`, `FFLCH`, `IME`). Se omitido, utiliza `config('services.dedalus.library')`.

#### Exemplo de Requisição

```http
GET /bibliografia/dedalus?search=Inteligência+artificial+na+educação+SILVA&library=EESC HTTP/1.1
Host: localhost:8000
Accept: application/json
```

#### Exemplo de Resposta (200 OK)

```json
{
  "success": true,
  "data": {
    "total": 1,
    "url": "https://app.abcd.usp.br/WSearch.php?base=DEDALUS&busca=...",
    "items": [
      {
        "sysno": "001234567",
        "title": "Inteligência artificial na educação",
        "author": "Silva, João",
        "year": "2024"
      }
    ]
  }
}
```

---

### 2.3 Revisão de Ementas (`/ementa`)

Avalia a qualidade e coerência de ementas acadêmicas em português e inglês, sugerindo versões revisadas e traduzindo trechos faltantes.

- **Método**: `GET` (informa os campos esperados) ou `POST` (processamento).
- **Parâmetros no POST**:
  - `mensagem` (*string*, obrigatório, máx. 10.000 caracteres): texto integral da ementa (em português e/ou inglês).

#### Exemplo de Requisição

```http
POST /ementa HTTP/1.1
Host: localhost:8000
Accept: application/json
Content-Type: application/json
X-CSRF-TOKEN: <token>

{
  "mensagem": "Disciplina de Computação Quântica. Objetivos: introduzir conceitos básicos de portas quânticas."
}
```

#### Exemplo de Resposta (Sucesso - 200 OK)

```json
{
  "success": true,
  "data": {
    "resposta": {
      "pt": "Disciplina de Computação Quântica. Objetivos: introduzir conceitos básicos de circuitos e portas quânticas.",
      "en": "Quantum Computing Course. Objectives: introduce fundamental concepts of quantum circuits and gates."
    },
    "score": {
      "pt": 8,
      "en": 7
    },
    "explicacoes": {
      "pt": "Ementa bem estruturada; sugerida expansão dos conceitos abordados.",
      "en": "Translated and adapted into academic English."
    },
    "analiseIngles": "ausente",
    "entrada": "Disciplina de Computação Quântica...",
    "estatisticas": {
      "prompt_tokens": 580,
      "completion_tokens": 120,
      "total_tokens": 700,
      "provider": "litellm",
      "model": "gemini-flash",
      "cached": false
    }
  }
}
```

---

### 2.4 Elaboração de Textos (`/texto`)

Elabora textos acadêmicos e institucionais estruturados a partir de tópicos, ideias soltas ou rascunhos.

- **Método**: `GET` (informa os campos esperados) ou `POST` (processamento).
- **Parâmetros no POST**:
  - `rascunho` (*string*, obrigatório, máx. 10.000 caracteres): tópicos, rascunho ou pontos-chave.
  - `style` (*string*, opcional, padrão: `'Automático'`): estilo desejado. Opções válidas em `TextoAgent::styles()`:
    - `Automático` (identifica o formato ideal com base no conteúdo e sugere assunto)
    - `Comunicado`
    - `Ofício`
    - `Despacho`
    - `Parecer`
    - `E-mail Institucional`
    - `Texto Acadêmico`
  - `contexto` (*string*, opcional, máx. 2.000 caracteres): informações complementares, público-alvo ou diretrizes específicas.

#### Exemplo de Requisição

```http
POST /texto HTTP/1.1
Host: localhost:8000
Accept: application/json
Content-Type: application/json
X-CSRF-TOKEN: <token>

{
  "rascunho": "Avisar os docentes do departamento que o prazo para entrega dos relatórios semestrais termina na próxima sexta-feira às 18h.",
  "style": "Comunicado",
  "contexto": "Departamento de Engenharia"
}
```

#### Exemplo de Resposta (Sucesso - 200 OK)

```json
{
  "success": true,
  "data": {
    "resposta": "Prezados(as) Docentes,\n\nInformamos que o prazo para envio dos relatórios de atividades semestrais encerra-se impreterivelmente na próxima sexta-feira, às 18h00...\n\nAtenciosamente,\nChefia de Departamento",
    "estiloUtilizado": "Comunicado",
    "assunto": "Prazo final para envio dos relatórios de atividades semestrais",
    "perguntas": [],
    "entrada": "Avisar os docentes...",
    "estilo": "Comunicado",
    "contexto": "Departamento de Engenharia",
    "estatisticas": {
      "prompt_tokens": 490,
      "completion_tokens": 140,
      "total_tokens": 630,
      "provider": "litellm",
      "model": "gemini-flash",
      "cached": false
    }
  }
}
```

---

## 3. Agentes de IA e Uso em Código PHP

Os agentes residem em `app/Ai/Agents/`, herdam de `Laravel\Ai\Agent` e implementam `Laravel\Ai\Contracts\HasStructuredOutput`. Eles utilizam o schema JSON definido em código para garantir a tipagem estrita da resposta da IA.

### 3.1 BibliografiaAgent

- **Construtor**: `new BibliografiaAgent(string $standard)`
- **Schema de saída**:
  - `referencias` (*array de strings*): referências normalizadas.
  - `explicacoes` (*array de strings*): justificativas e apontamentos sobre cada item.
  - `busca_dedalus` (*array de strings*): termos sintéticos para busca no catálogo.
  - `confiancas` (*array de strings*): `'alta'`, `'media'` ou `'baixa'`.

### 3.2 EmentaAgent

- **Construtor**: `new EmentaAgent()`
- **Schema de saída**:
  - `score` (*objeto { pt: int, en: int }*): pontuação de 0 a 10 para clareza e estrutura.
  - `explicacoes` (*objeto { pt: string, en: string }*): parecer técnico detalhado.
  - `texto_revisado` (*objeto { pt: string, en: string }*): texto aprimorado da ementa.
  - `status_ingles` (*string*): `'presente'`, `'parcial'` ou `'ausente'`.

### 3.3 TextoAgent

- **Construtor**: `new TextoAgent(string $style = 'Automático', ?string $context = null)`
- **Schema de saída**:
  - `texto_elaborado` (*string*): texto formal redigido.
  - `estilo_utilizado` (*string*): estilo efetivamente aplicado.
  - `assunto` (*string*): sugestão de assunto ou título do documento.
  - `perguntas_esclarecimento` (*array de strings*): dúvidas ou lacunas identificadas no rascunho.

---

### 3.4 Exemplo de Invocação via AiService

O `AiService` orquestra a chamada ao agente, lida com versionamento de prompts via banco (`AiPrompt`), consulta o cache configurado e coleta métricas de execução:

```php
use App\Ai\Agents\BibliografiaAgent;
use App\Ai\Agents\EmentaAgent;
use App\Ai\Agents\TextoAgent;
use App\Services\AiService;

$service = app(AiService::class);

// 1. Normalização Bibliográfica
$resultadoBibliografia = $service->submit(
    new BibliografiaAgent('ABNT NBR 6023:2025'),
    $textoReferencias
);

// 2. Revisão de Ementa
$resultadoEmenta = $service->submit(
    new EmentaAgent(),
    $textoEmenta
);

// 3. Elaboração de Texto
$resultadoTexto = $service->submit(
    new TextoAgent('Ofício', 'Público: Diretoria da Unidade'),
    $rascunhoTexto
);
```

---

### 3.5 Estatísticas de Execução e Cache

Após cada chamada `$service->submit()`, o método `$service->statistics()` disponibiliza as métricas da operação:

```php
$stats = $service->statistics();
/*
[
    'prompt_tokens'     => 520,
    'completion_tokens' => 110,
    'total_tokens'      => 630,
    'provider'          => 'litellm',
    'model'             => 'gemini-flash',
    'id'                => 'chatcmpl-9x...',
    'finish_reason'     => 'stop',
    'duration'          => 1.45,
    'cached'            => false,
    'cached_at'         => null
]
*/
```

Em acertos de cache (`cached === true`), a resposta é retornada instantaneamente da memória/storage e `cached_at` indica a data da geração original.

---

## 4. Tratamento de Erros e Códigos HTTP

As respostas de erro JSON seguem um envelope uniforme:

```json
{
  "success": false,
  "message": "Mensagem detalhada do erro ou falha."
}
```

### Códigos de Status HTTP

| Código | Nome | Causa |
| :--- | :--- | :--- |
| **401** | Unauthorized | Requisição sem autenticação (sessão ausente ou inválida). |
| **419** | Page Expired / CSRF Error | Token CSRF ausente ou inválido em requisições `POST`. |
| **422** | Unprocessable Content | Falha na validação de entrada (ex.: campo obrigatório ausente, texto excedendo 10.000 caracteres, norma não suportada). |
| **429** | Too Many Requests | Limite de taxa de requisições do gateway LiteLLM ou do provedor LLM atingido. |
| **502** | Bad Gateway | Provedor retornou resposta inválida, não estruturada de acordo com o schema, ou falha de inferência. |
| **503** | Service Unavailable | Gateway LiteLLM inacessível, falha de rede ou chaves de API não configuradas no `.env`. |
| **504** | Gateway Timeout | Tempo limite de execução (60 segundos) excedido pelo provedor LLM. |

---

## 5. Documentação Técnica Correlata

- **[Arquitetura Geral e Integração com o Laravel AI SDK](implementacao-laravel-ai-sdk.md)**
- **[Serviço de Bibliografia](bibliografia.md)**
- **[Serviço de Ementa](ementa.md)**
- **[Serviço de Elaboração de Texto](texto.md)**

