<?php

namespace App\Ai\Agents;

use App\Models\AiPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\AgentInput;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

/**
 * Formata referências com o prompt de bibliografia e uma saída estruturada de quatro listas.
 *
 * A norma é fornecida pelo chamador; o texto a revisar é enviado a prompt().
 * O agente executa chamadas independentes, sem contexto de conversa ou tools.
 */
class BibliografiaAgent implements Agent, HasStructuredOutput
{
    /** @var string Identificador do serviço de bibliografia na tabela `ai_prompts`. */
    public const SLUG = 'bibliografia';

    /** @var list<string> Normas bibliográficas habilitadas por padrão quando não sobrescritas no banco. */
    public const DEFAULT_STANDARDS = [
        'ABNT NBR 6023:2025',
        'APA 7th Edition',
        // 'Chicago Manual of Style 17th Edition',
        // 'MLA 9th Edition',
        // 'Harvard Referencing Style',
        'Vancouver Style',
        'IEEE Citation Style',
        // 'Turabian 9th Edition',
        // 'AMA Manual of Style 11th Edition',
        // 'CSE (Council of Science Editors) Style'
    ];

    /**
     * Preserva o método original do SDK com outro nome para que
     * a implementação personalizada de prompt() possa reutilizá-lo.
     *
     * O alias é privado para impedir chamadas externas que
     * contornem a validação das listas.
     */
    use Promptable {
        prompt as private promptViaSdk;
    }

    /**
     * Define a norma usada na interpolação do prompt e, opcionalmente, uma versão específica.
     *
     * @param string $standard Norma bibliográfica previamente validada pelo chamador.
     * @param int|null $version Versão específica do prompt no banco (opcional; `null` usa a ativa).
     * @return void
     */
    public function __construct(
        private string $standard,
        private ?int $version = null,
    ) {}

    /**
     * Retorna a lista de normas bibliográficas suportadas pelo agente.
     *
     * Consulta primeiro os metadados da versão ativa no banco de dados (`metadata['standards']`),
     * recorrendo à constante `DEFAULT_STANDARDS` caso não haja sobrescrita no banco.
     *
     * @return list<string> Normas de formatação bibliográfica habilitadas.
     */
    public static function standards(): array
    {
        $active = AiPrompt::findActive(self::SLUG);
        $standards = $active?->metadata['standards'] ?? null;

        if (is_array($standards) && $standards !== []) {
            return array_values($standards);
        }

        return self::DEFAULT_STANDARDS;
    }

    /**
     * Retorna o template padrão (v1) de instruções de bibliografia para seeder e fallback.
     *
     * @return string Template do system prompt de bibliografia com placeholders `{$standard}`.
     */
    public static function defaultTemplate(): string
    {
        return <<<'PROMPT'
You are a precise bibliographic formatting assistant following the {$standard} standard
OUTPUT FORMAT RULE:
You MUST respond STRICTLY with a valid JSON object. Do NOT include markdown code block wrappers (like ```json). The JSON must have exactly four root keys:
1. "referencias": An array of strings containing ONLY the corrected, standardized references.
2. "explicacoes": An array of strings explaining ONLY the specific adjustments, corrections, or missing fields. Do NOT include generic introductory phrases.
3. "busca_dedalus": An array of objects, one for each reference, optimized for library catalog lookup with two keys:
   - "autor": The primary author's name cleaned (e.g., "FARRET, F. A." or "ANDERSEN, James M."). If published by an organization, use the entity name.
   - "titulo": The full main title including subtitle if present, excluding edition numbers or 'In:' prefixes.
4. "confiancas": An array of integers from 0 to 100, representing the quality and completeness of the ORIGINAL input reference. High values (e.g. 90-100) indicate the input was nearly complete and required minimal changes. Low values (e.g. 0-50) indicate the original input was poor, highly incomplete, or required heavy modifications.

FORMATTING RULES:
- EXPLICIT RULES PRECEDENCE: Strictly adhere to all explicit formatting rules listed below. Always prioritize these explicit instructions over general knowledge or default guidelines whenever a conflict or ambiguity arises.
- PAGE NUMBERS & PAGINATION: Preserve total page counts (e.g., "295 p." or "295p.") as total page counts. Do NOT convert a total page count into a page range (e.g., do NOT turn "295 p." into "1-295" or "[1]-295"). Do NOT infer or add bracketed starting page numbers.
- DIRECT EXPLANATIONS ONLY: In "explicacoes", start IMMEDIATELY with the specific change, correction, or missing element. NEVER include boilerplate introductory text like "Ajustado para o padrão ABNT", "Normalizado de acordo com...", or "Referência formatada".
- AUTHOR LIMIT & ET AL.: Count distinct human authors carefully. List up to five authors explicitly (separated by semicolons or standard standard-specific delimiters). Use "et al." ONLY when there are 6 or more distinct authors. Ignore typos like "et Cal." or "et all." embedded in raw author names.
- PLAIN TEXT ONLY: "referencias" values must contain plain text only. Do NOT use Markdown, HTML, or formatting syntax.
- DO NOT USE BOLD: Never use **, __, <strong>, <b>, or other markup for emphasis.
- ONE REFERENCE PER ARRAY ELEMENT: Each element must contain exactly one complete bibliographic reference.
- OUTPUT WHITESPACE: Each reference must be a single continuous line. Do NOT insert blank lines, paragraph spacing, indentation, or unnecessary whitespace.
- LINE BREAK HANDLING: Treat line breaks within the same reference as unintended text wraps. Merge broken lines into a single continuous text string. Do NOT split a reference into multiple entries because of line breaks.
- DO NOT GUESS OR INFER UNKNOWN YEARS: If the publication year is missing, use '[s.d.]' or omit the date field. Never invent years.
- WEBSITES & URLS: Format URLs enclosed in '<>'. If 'Acesso em:' is not explicitly provided in the input, omit it entirely. NEVER output 'Acesso em: [s.d.]'.
- BOOK CHAPTERS: Format according to the specified {$standard} standard. Do NOT use bold or other visual emphasis for the book title or chapter title.
- REMOVE UNWANTED CHARACTERS: Remove pilcrows (¶), Markdown markers, HTML tags, and other formatting artifacts.
- CORRECTIONS: Correct obvious typographical errors in words, names, titles, publishers, and other bibliographic elements when the intended information is clear.
- PRESERVE INFORMATION: Do not remove valid bibliographic information merely to simplify the reference.
- MISSING INFORMATION: Do not fabricate bibliographic information. When an element cannot be determined from the input, use the appropriate indication required by {{ $standard }} or omit the element when permitted.
- CONSISTENCY: Apply the same formatting rules consistently to all references.
- CORRESPONDENCE: "explicacoes", "busca_dedalus", and "confiancas" must contain exactly one corresponding item for each element in "referencias".
- CONFIDENCE: Assign a value from 0 to 100 based on the quality, structure, and completeness of the ORIGINAL input. Deduct points for missing mandatory fields (like year, publisher, author), severe formatting defects, or when heavy modifications/inferencing were required to fit the {{ $standard }} standard. A score of 100 means the input was already well-formatted and complete.
PROMPT;
    }

    /**
     * Resolve as instruções de bibliografia a partir do banco de dados (ou fallback padrão).
     *
     * @return string Prompt resolvido com a norma selecionada interpolada.
     */
    public function instructions(): string
    {
        return AiPrompt::resolve(
            self::SLUG,
            self::defaultTemplate(),
            ['standard' => $this->standard],
            $this->version,
        );
    }

    /**
     * Declara o formato já descrito no prompt, preservando as quatro listas atuais.
     *
     * @param JsonSchema $schema Construtor de tipos JSON Schema fornecido pelo SDK.
     * @return array<string, Type> Campos obrigatórios e tipos da resposta de bibliografia.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'referencias' => $schema->array()->items($schema->string())->required(),
            'explicacoes' => $schema->array()->items($schema->string())->required(),
            'busca_dedalus' => $schema->array()->items(
                $schema->object([
                    'autor' => $schema->string()->required(),
                    'titulo' => $schema->string()->required(),
                ]),
            )->required(),
            'confiancas' => $schema->array()->items(
                $schema->integer()->min(0)->max(100),
            )->required(),
        ];
    }

    /**
     * Executa a chamada síncrona do SDK e confere a correspondência entre as listas.
     *
     * @param AgentInput|UserMessage|Decisions|string $prompt Entrada aceita pela interface
     *     do SDK; neste fluxo, contém o texto das referências como string.
     * @param array $attachments Anexos previstos na interface do SDK; não usados neste fluxo.
     * @param Lab|array|string|null $provider Provider opcional; null usa config('ai.default').
     * @param string|null $model Modelo opcional; null usa o padrão do provider selecionado.
     * @param int|null $timeout Timeout opcional em segundos; null usa o padrão do SDK.
     * @return StructuredAgentResponse Dados estruturados e metadados da execução.
     * @throws RuntimeException Quando a resposta não for estruturada ou as listas não corresponderem.
     * @throws \Laravel\Ai\Exceptions\AiException Quando o SDK falhar ao executar o provider.
     */
    public function prompt(
        AgentInput|UserMessage|Decisions|string $prompt,
        array $attachments = [],
        Lab|array|string|null $provider = null,
        ?string $model = null,
        ?int $timeout = null,
    ): StructuredAgentResponse {
        // Delega a comunicação ao SDK, mantendo sua assinatura e opções de execução.
        $response = $this->promptViaSdk($prompt, $attachments, $provider, $model, $timeout);

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('A resposta de bibliografia não contém dados estruturados.');
        }

        // O JSON Schema valida os tipos, mas não garante que as quatro
        // listas possuam o mesmo tamanho. Essa verificação é feita aqui.
        $this->validateCorrespondence($response);

        return $response;
    }

    /**
     * Confere que as quatro listas permitem associar um item por referência.
     *
     * @param StructuredAgentResponse $response Resposta estruturada a ser verificada.
     * @return void
     * @throws RuntimeException Quando uma lista estiver ausente, inválida ou tiver tamanho diferente.
     */
    private function validateCorrespondence(StructuredAgentResponse $response): void
    {
        $result = $response->toArray();
        $referenceCount = null;

        // JSON Schema define cada lista, mas não compara o tamanho de campos diferentes.
        foreach (['referencias', 'explicacoes', 'busca_dedalus', 'confiancas'] as $field) {
            $items = $result[$field] ?? null;

            if (! is_array($items) || ! array_is_list($items)) {
                throw new RuntimeException("A resposta de bibliografia deve conter a lista '{$field}'.");
            }

            $referenceCount ??= count($items);

            if (count($items) !== $referenceCount) {
                throw new RuntimeException(
                    "A lista '{$field}' deve conter um item para cada referência.",
                );
            }
        }
    }
}
