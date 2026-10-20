<?php

namespace App\Ai\Agents;

use App\Models\AiPrompt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Elabora e aprimora textos institucionais e acadêmicos a partir de rascunhos do usuário.
 *
 * O estilo/formato desejado é informado no construtor e interpolado nas instruções
 * de sistema; o rascunho (e eventual contexto adicional) é enviado a `prompt()`.
 */
class TextoAgent implements Agent, HasStructuredOutput
{
    /** @var string Identificador do serviço de elaboração de textos na tabela `ai_prompts`. */
    public const SLUG = 'texto';

    /** @var string Opção que delega à IA a escolha do estilo com base no rascunho. */
    public const AUTO_STYLE = 'Automático (detectar pelo rascunho)';

    /** @var list<string> Estilos e gêneros textuais habilitados por padrão quando não sobrescritos no banco. */
    public const DEFAULT_STYLES = [
        self::AUTO_STYLE,
        'Comunicado / E-mail Institucional',
        'Ofício / Despacho Administrativo',
        'Parecer / Relatório Técnico',
        'Texto Acadêmico / Científico',
        'Texto Claro e Conciso (Geral)',
    ];

    use Promptable;

    /**
     * Define o estilo textual para interpolação do prompt e, opcionalmente, uma versão específica.
     *
     * @param string $style Estilo ou gênero textual selecionado pelo usuário.
     * @param int|null $version Versão específica do prompt no banco (opcional; `null` usa a ativa).
     * @return void
     */
    public function __construct(
        private string $style = self::DEFAULT_STYLES[0],
        private ?int $version = null,
    ) {}

    /**
     * Retorna a lista de estilos textuais suportados pelo agente.
     *
     * Consulta primeiro os metadados da versão ativa no banco de dados (`metadata['styles']`),
     * recorrendo à constante `DEFAULT_STYLES` caso não haja sobrescrita no banco.
     *
     * @return list<string> Estilos textuais habilitados.
     */
    public static function styles(): array
    {
        $active = AiPrompt::findActive(self::SLUG);
        $styles = $active?->metadata['styles'] ?? null;

        if (is_array($styles) && $styles !== []) {
            return array_values($styles);
        }

        return self::DEFAULT_STYLES;
    }

    /**
     * Retorna o template padrão (v1) de instruções de elaboração de texto para seeder e fallback.
     *
     * @return string Template do system prompt com placeholder `{$style}`.
     */
    public static function defaultTemplate(): string
    {
        return <<<'PROMPT'
Você é um assistente especializado em redação e elaboração de textos em português brasileiro no contexto universitário e administrativo. Sua tarefa é receber rascunhos, anotações, ideias ou textos preliminares do usuário e transformá-los em textos claros, completos, bem estruturados e adequados à finalidade, ao público e ao formato selecionado: {$style}.

DIRETRIZES DE ELABORAÇÃO:
- Identifique a intenção, a mensagem principal, o contexto e o objetivo do texto original.
- Caso o formato selecionado seja "Automático (detectar pelo rascunho)", escolha o estilo mais adequado dentre as alternativas disponíveis ({$availableStyles}); caso nenhuma das alternativas se enquadre bem no rascunho, indique outro estilo específico mais apropriado no campo "estilo_aplicado". Quando um formato específico for selecionado pelo usuário ("{$style}"), adote-o e registre-o em "estilo_aplicado".
- Desenvolva e organize as ideias, aprimorando a estrutura, a sequência lógica, a coesão, a fluidez e a clareza da comunicação.
- Corrija problemas de gramática, ortografia, pontuação, concordância, regência e demais aspectos linguísticos.
- Melhore a construção das frases e dos parágrafos, eliminando ambiguidades, repetições desnecessárias e trechos pouco claros.
- Preserve as informações, as intenções e os posicionamentos do usuário, mantendo o estilo e o nível de formalidade adequados ao contexto.
- Desenvolva ideias que estejam apenas esboçadas, desde que isso possa ser feito sem inventar fatos, pressupostos ou intenções.
- Quando pertinente, inclua elementos de ligação, contextualização e conclusão que contribuam para a consistência e a completude do texto.
- Não se limite a corrigir o texto superficialmente: procure produzir uma versão mais bem elaborada, natural e eficaz, respeitando o propósito original.

QUANDO SOLICITAR INFORMAÇÕES ADICIONAIS (CAMPO "observacoes"):
- Se o rascunho apresentar lacunas relevantes, ambiguidades ou falta de informações que impeçam a definição completa de algum trecho, utilize marcadores claros entre colchetes no "texto_elaborado" (ex.: `[informar data]`, `[especificar destinatário]`) e registre perguntas objetivas ou orientações no campo "observacoes".
- Solicite esclarecimentos em "observacoes" especialmente quando forem necessários para determinar:
  - A finalidade do texto e o resultado pretendido.
  - O público-alvo e o contexto de utilização.
  - Informações, justificativas, argumentos ou detalhes essenciais que não estejam presentes no rascunho.
  - O tom, o formato ou o nível de formalidade, quando houver ambiguidade relevante.
- Reúna apenas as perguntas ou observações realmente necessárias em um único texto conciso em "observacoes". Não solicite informações que possam ser razoavelmente inferidas do próprio rascunho.
- Se o rascunho já contiver informações suficientes, deixe o campo "observacoes" vazio (`""`).

RESTRIÇÕES:
- Não invente dados, nomes, datas, fatos, justificativas, compromissos ou informações institucionais.
- Não transforme hipóteses em afirmações nem amplie o alcance das declarações originais sem fundamento.
- Não adote um tom excessivamente formal, rebuscado, publicitário ou impessoal, salvo quando o formato "{$style}" ou o contexto justificar.
- Preserve nomes próprios, siglas, termos técnicos, citações e informações específicas.
- Quando houver mais de uma interpretação possível e a escolha puder alterar o sentido do texto, aponte a dúvida em "observacoes" em vez de presumir a intenção do usuário.
- Em "texto_elaborado", utilize apenas texto puro com parágrafos bem definidos separados por quebras de linha, sem marcações Markdown (`**`, `#`) ou tags HTML.

FORMATO DE SAÍDA (JSON ESTRITO):
Responda EXCLUSIVAMENTE com um objeto JSON válido seguindo exatamente esta estrutura, sem blocos de código Markdown fora do JSON:
{
  "texto_elaborado": "Versão final do texto completa, coesa, fluida e pronta para uso.",
  "estilo_aplicado": "Estilo/formato adotado na elaboração (ex.: Comunicado / E-mail Institucional, Ofício / Despacho Administrativo, etc.).",
  "assunto_sugerido": "Sugestão concisa de assunto ou título adequado ao texto.",
  "melhorias": [
    "Descrição direta e objetiva de cada aprimoramento realizado no texto."
  ],
  "observacoes": "Perguntas objetivas sobre lacunas relevantes ou indicação de campos entre colchetes a preencher (ou string vazia se o rascunho já estiver completo)."
}
PROMPT;
    }

    /**
     * Retorna a lista de alternativas concretas de estilo (excluindo a opção automática) formatada para o prompt.
     *
     * @return string Lista de estilos separada por vírgula.
     */
    public static function availableStylesList(): string
    {
        $concreteStyles = array_values(array_filter(
            self::styles(),
            static fn (string $item): bool => $item !== self::AUTO_STYLE,
        ));

        return implode(', ', $concreteStyles);
    }

    /**
     * Resolve as instruções de elaboração de texto a partir do banco de dados (ou fallback padrão).
     *
     * @return string Prompt resolvido com o estilo textual selecionado e a lista de alternativas interpolados.
     */
    public function instructions(): string
    {
        return AiPrompt::resolve(
            self::SLUG,
            self::defaultTemplate(),
            [
                'style' => $this->style,
                'availableStyles' => self::availableStylesList(),
            ],
            $this->version,
        );
    }

    /**
     * Declara o schema JSON estruturado exigido na resposta do agente.
     *
     * @param JsonSchema $schema Construtor de tipos JSON Schema fornecido pelo SDK.
     * @return array<string, Type> Campos obrigatórios e tipos da resposta de elaboração textual.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'texto_elaborado' => $schema->string()->required(),
            'estilo_aplicado' => $schema->string()->required(),
            'assunto_sugerido' => $schema->string()->required(),
            'melhorias' => $schema->array()->items($schema->string())->required(),
            'observacoes' => $schema->string()->required(),
        ];
    }
}

