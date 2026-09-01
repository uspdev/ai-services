<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AiPrompt extends Model
{
    protected $fillable = [
        'slug',
        'nome',
        'descricao',
        'system_content',
        'user_content'
    ];

    public static function get(string $slug): string
    {
        return match ($slug) {
            'bibliografia' => self::bibliografiaContent(),
            'traducao' => '',
            default => throw new InvalidArgumentException(
                "Prompt '{$slug}' não encontrado."
            ),
        };
    }

    protected static function bibliografiaContent(): string
    {
        $standard = 'ABNT NBR 6023:2025';
        return <<<PROMPT
You are a precise bibliographic formatting assistant following the $standard standard
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
- BOOK CHAPTERS: Format according to the specified {{ $standard }} standard. Do NOT use bold or other visual emphasis for the book title or chapter title.
- REMOVE UNWANTED CHARACTERS: Remove pilcrows (¶), Markdown markers, HTML tags, and other formatting artifacts.
- CORRECTIONS: Correct obvious typographical errors in words, names, titles, publishers, and other bibliographic elements when the intended information is clear.
- PRESERVE INFORMATION: Do not remove valid bibliographic information merely to simplify the reference.
- MISSING INFORMATION: Do not fabricate bibliographic information. When an element cannot be determined from the input, use the appropriate indication required by {{ $standard }} or omit the element when permitted.
- CONSISTENCY: Apply the same formatting rules consistently to all references.
- CORRESPONDENCE: "explicacoes", "busca_dedalus", and "confiancas" must contain exactly one corresponding item for each element in "referencias".
- CONFIDENCE: Assign a value from 0 to 100 based on the quality, structure, and completeness of the ORIGINAL input. Deduct points for missing mandatory fields (like year, publisher, author), severe formatting defects, or when heavy modifications/inferencing were required to fit the {{ $standard }} standard. A score of 100 means the input was already well-formatted and complete.
PROMPT;
    }

    protected static function revisaoGramaticalContent(?string $disciplina = null, ?string $area = null): string
    {
        // Constrói o bloco de contexto apenas se houver dados válidos
        $contexto = '';

        $infoDisciplina = ($disciplina && $disciplina !== 'null') ? trim($disciplina) : null;
        $infoArea = ($area && $area !== 'null') ? trim($area) : null;

        if ($infoDisciplina || $infoArea) {
            $contexto = "CONTEXTO DO TEXTO AVALIADO:\n";
            if ($infoDisciplina) {
                $contexto .= "- Disciplina: {$infoDisciplina}\n";
            }
            if ($infoArea) {
                $contexto .= "- Área do Conhecimento: {$infoArea}\n";
            }
            $contexto .= "\nUtilize este contexto para alinhar a terminologia técnica, o nível acadêmico e o jargão específico da área.\n";
        }

        return <<<PROMPT
Você é um Revisor Acadêmico e Pedagógico Especialista em Design Curricular, cobrindo todas as áreas do conhecimento (Exatas, Humanas, Biológicas, Sociais Aplicadas e Engenharias).

Sua função é avaliar, diagnosticar e revisar ementas, programas de disciplina e textos pedagógicos/acadêmicos enviados pelo usuário.

{$contexto}
Sua resposta DEVE ser estruturada rigorosamente nas 3 seções a seguir:

1. SCORE DE QUALIDADE E NECESSIDADE DE ALTERAÇÕES
   - Apresente um Score de 0 a 100 baseado na qualidade atual e na quantidade/severidade de ajustes necessários no texto original:
     * 90–100: Excelente (Ajustes mínimos ou nulos; apenas retoques pontuais).
     * 70–89: Bom (Pequenas correções gramaticais, de formatação ou de clareza).
     * 50–69: Regular (Erros gramaticais evidentes, falhas de terminologia ou redundâncias).
     * < 50: Insuficiente (Ajustes estruturais profundos, problemas graves de redação ou imprecisão técnica).
   - Indique o Score e forneça um resumo executivo de 1 a 2 frases justificando a nota.

2. ANÁLISE E EXPLICAÇÕES DETALHADAS
   Apresente as correções necessárias organizadas em tópicos claros:
   a) Gramática, Acentuação e Pontuação: Erros de ortografia, concordância, crase e padronização visual de pontuação (ex.: pontuação final dos itens).
   b) Redação Acadêmica e Clareza Pedagógica: Problemas de estilo, prolixidade, redundâncias ou falta de paralelismo sintático.
   c) Terminologia Técnica e Vocabulário de Campo: Imprecisões conceituais relativas à área do conhecimento do texto (seja em Português, Inglês ou outro idioma).

3. EMENTA / TEXTO REVISADO (VERSÃO FINAL)
   - Apresente o texto final totalmente corrigido, polido e padronizado.
   - Mantenha a estrutura original (ex.: lista numerada de tópicos) com pontuação e tipografia perfeitamente homogêneas, pronto para inclusão em um Projeto Pedagógico de Curso (PPC) ou Syllabus.

Tom de voz: Neutro, profissional, direto e fundamentado nas boas práticas de redação acadêmica e design curricular.
PROMPT;
    }

    /**
     * Renderiza um prompt para envio à API de IA, combinando as instruções
     * de sistema com o conteúdo fornecido pelo usuário.
     *
     * @param string $slug Identificador do prompt a ser utilizado.
     * @param string $userContent Conteúdo fornecido pelo usuário.
     * @return array Mensagens no formato esperado pela API Chat Completions.
     *
     * @throws \InvalidArgumentException Quando o prompt informado não existir.
     */
    public static function render(string $slug, string $userContent): array
    {
        return [
            [
                'role' => 'system',
                'content' => self::get($slug),
            ],
            [
                'role' => 'user',
                'content' => $userContent,
            ],
        ];
    }

    /**
     * Retorna as instruções de sistema associadas a um prompt.
     *
     * @param string $slug Identificador do prompt.
     * @return string Instruções de sistema utilizadas pela IA.
     *
     * @throws \InvalidArgumentException Quando o prompt informado não existir.
     */
    public static function systemPrompt(string $slug): string
    {
        return match ($slug) {
            'bibliografia' => self::bibliografiaContent(),

            default => throw new InvalidArgumentException(
                "Prompt '{$slug}' não encontrado."
            ),
        };
    }
}
