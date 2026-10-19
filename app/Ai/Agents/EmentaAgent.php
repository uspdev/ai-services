<?php

namespace App\Ai\Agents;

use App\Models\AiPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Revisa ementas com o prompt de revisão acadêmica e uma saída estruturada em português e inglês.
 *
 * O texto a revisar é enviado a prompt(). Cada chamada é independente, sem
 * contexto de conversa, tools ou parâmetros adicionais de disciplina e área.
 */
class EmentaAgent implements Agent, HasStructuredOutput
{
    /** @var string Identificador do serviço de ementa na tabela `ai_prompts`. */
    public const SLUG = 'ementa';

    use Promptable;

    /**
     * Define opcionalmente uma versão específica do prompt para execução ou homologação.
     *
     * @param int|null $version Versão específica do prompt no banco (opcional; `null` usa a ativa).
     * @return void
     */
    public function __construct(
        private ?int $version = null,
    ) {}

    /**
     * Retorna o template padrão (v1) de instruções de revisão de ementas para seeder e fallback.
     *
     * @return string Template do system prompt de revisão de ementas.
     */
    public static function defaultTemplate(): string
    {
        return <<<'PROMPT'
You are an Academic and Curriculum Reviewer specializing in curriculum design across all fields, including STEM, Humanities, Life Sciences, Social Sciences, and Engineering.

Review course descriptions, learning objectives, syllabi, and academic/pedagogical texts in the context of the University of São Paulo (USP).

INPUT:
The user may provide:

1. Structured JSON with fields such as `ementa`, `objetivos`, and `conteudo_programatico`, optionally containing `pt` and `en`.
2. Free text using labels such as Ementa/Course Description, Objetivos/Objectives, and Conteúdo Programático/Full Program, including Markdown or tag delimiters.

Identify the Portuguese and English versions of each section:

- Ementa / Course Description
- Objetivos / Objectives
- Conteúdo Programático / Full Program

If an English section is missing, empty, or contains only its default heading, set its status to `ausente` and provide an accurate technical translation.
If English is present, assess its technical and semantic equivalence to Portuguese and revise it when necessary.

EVALUATION:

1. QUALITY SCORE (0–100)

- 90–100: Excellent; minimal or no changes.
- 70–89: Good; minor grammar, formatting, or clarity issues.
- 50–69: Fair; noticeable grammar, terminology, clarity, or redundancy issues.
- Below 50: Insufficient; major structural, writing, or technical problems.

Provide a 1–2 sentence justification.

2. ANALYSIS

Evaluate each language separately:

Portuguese (`pt`):

- Grammar, spelling, agreement, and punctuation.
- Academic writing, pedagogical clarity, conciseness, redundancy, and syntactic parallelism.
- Technical terminology and conceptual precision.

English (`en`):

- `status`: `presente` = complete; `parcial` = incomplete or missing parts; `ausente` = no meaningful English text.
- Assess spelling, grammar, punctuation, academic tone, clarity, PT equivalence, and field-specific terminology.

3. REVISION

Provide final polished versions in Portuguese and English for all three sections. Ensure technical accuracy, consistency, homogeneous style, and suitability for inclusion in a PPC or syllabus.

Use a neutral, professional, concise, and evidence-based academic tone.

OUTPUT:
Return ONLY a valid JSON object. No Markdown or text outside the JSON. Follow this exact schema:

{
  "score": {
    "valor": 0,
    "justificativa": "1–2 sentence executive summary justifying the score."
  },
  "explicacoes": {
    "pt": {
      "gramatica_acentuacao_pontuacao": "Portuguese grammar, spelling, agreement, and punctuation analysis.",
      "redacao_academica_clareza_pedagogica": "Portuguese academic style, clarity, redundancy, conciseness, and parallelism analysis.",
      "terminologia_tecnica": "Portuguese technical terminology and conceptual precision analysis."
    },
    "en": {
      "status": "presente | parcial | ausente",
      "gramatica_acentuacao_pontuacao": "English spelling, grammar, and punctuation analysis, or explanation if absent.",
      "redacao_academica_clareza_pedagogica": "English academic tone, clarity, and equivalence with Portuguese.",
      "terminologia_tecnica": "English technical terminology and field-specific vocabulary analysis."
    }
  },
  "texto_revisado": {
    "ementa": {
      "pt": "Final revised Portuguese text.",
      "en": "Final revised or translated English text."
    },
    "objetivos": {
      "pt": "Final revised Portuguese text.",
      "en": "Final revised or translated English text."
    },
    "conteudo_programatico": {
      "pt": "Final revised Portuguese text.",
      "en": "Final revised or translated English text."
    }
  }
}
PROMPT;
    }

    /**
     * Resolve as instruções de revisão acadêmica a partir do banco de dados (ou fallback padrão).
     *
     * @return string Prompt resolvido de ementa, incluindo seu exemplo de JSON.
     */
    public function instructions(): string
    {
        return AiPrompt::resolve(
            self::SLUG,
            self::defaultTemplate(),
            [],
            $this->version,
        );
    }

    /**
     * Declara os objetos e campos obrigatórios já descritos no prompt de ementa.
     *
     * @param JsonSchema $schema Construtor de tipos JSON Schema fornecido pelo SDK.
     * @return array<string, Type> Avaliação, explicações e versões revisadas nos dois idiomas.
     */
    public function schema(JsonSchema $schema): array
    {
        // As duas análises usam os mesmos campos; somente a inglesa possui status.
        $analysisFields = [
            'gramatica_acentuacao_pontuacao' => $schema->string()->required(),
            'redacao_academica_clareza_pedagogica' => $schema->string()->required(),
            'terminologia_tecnica' => $schema->string()->required(),
        ];

        // Cada seção revisada deve incluir português e inglês, mesmo quando o
        // inglês estava ausente na entrada e precisa ser traduzido pelo modelo.
        $bilingualFields = [
            'pt' => $schema->string()->required(),
            'en' => $schema->string()->required(),
        ];

        return [
            'score' => $schema->object([
                'valor' => $schema->integer()->min(0)->max(100)->required(),
                'justificativa' => $schema->string()->required(),
            ])->required(),
            'explicacoes' => $schema->object([
                'pt' => $schema->object($analysisFields)->required(),
                'en' => $schema->object([
                    'status' => $schema->string()->enum(['presente', 'parcial', 'ausente'])->required(),
                    ...$analysisFields,
                ])->required(),
            ])->required(),
            'texto_revisado' => $schema->object([
                'ementa' => $schema->object($bilingualFields)->required(),
                'objetivos' => $schema->object($bilingualFields)->required(),
                'conteudo_programatico' => $schema->object($bilingualFields)->required(),
            ])->required(),
        ];
    }
}
