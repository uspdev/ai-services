<?php

namespace Tests\Feature;

use App\Ai\Agents\TextoAgent;
use App\Models\AiPrompt;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\StructuredAgentResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifica o contrato do agente de elaboração de textos, a interpolação do estilo e o schema JSON.
 *
 * As chamadas são simuladas para não utilizar credenciais ou serviços pagos.
 */
class TextoAgentTest extends TestCase
{
    /**
     * Configura um gateway de teste e bloqueia requisições HTTP não simuladas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.default' => 'litellm',
            'ai.providers.litellm' => [
                'driver' => 'openai-compatible',
                'url' => 'https://litellm.example.com/v1',
                'key' => 'sdk-test-key',
                'models' => ['text' => ['default' => 'sdk-test-model']],
            ],
        ]);
        Http::preventStrayRequests();
    }

    /**
     * Confere o envio do prompt interpolado com o estilo selecionado e o schema estruturado.
     *
     * @param string $style Estilo textual suportado pela aplicação.
     * @return void
     */
    #[DataProvider('textStyles')]
    public function test_agent_sends_the_interpolated_prompt_and_structured_schema(string $style): void
    {
        $result = self::textoResult();
        Http::fake([
            '*' => Http::response([
                'model' => 'sdk-test-model',
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode($result),
                    ],
                ]],
                'usage' => ['prompt_tokens' => 25, 'completion_tokens' => 15],
            ]),
        ]);

        $agent = new TextoAgent($style);
        $input = 'Solicito prorrogação do prazo de entrega do relatório.';
        $expectedPrompt = AiPrompt::systemPrompt('texto', ['style' => $style]);
        $response = $agent->prompt($input);

        $this->assertSame($expectedPrompt, $agent->instructions());
        $this->assertStringContainsString($style, $agent->instructions());
        $this->assertInstanceOf(StructuredAgentResponse::class, $response);
        $this->assertSame($result, $response->toArray());
        $this->assertSame(40, $response->usage->totalTokens());
        Http::assertSentCount(1);

        [$request] = Http::recorded()->first();
        $this->assertSame('https://litellm.example.com/v1/chat/completions', $request->url());
        $this->assertSame([
            ['role' => 'system', 'content' => $expectedPrompt],
            ['role' => 'user', 'content' => $input],
        ], $request['messages']);
        $this->assertSame('json_schema', $request['response_format']['type']);

        $schema = $request['response_format']['json_schema']['schema'];
        $this->assertSame(
            ['texto_elaborado', 'estilo_aplicado', 'assunto_sugerido', 'melhorias', 'observacoes'],
            $schema['required']
        );
        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame('string', $schema['properties']['texto_elaborado']['type']);
        $this->assertSame('string', $schema['properties']['estilo_aplicado']['type']);
        $this->assertSame('string', $schema['properties']['assunto_sugerido']['type']);
        $this->assertSame('array', $schema['properties']['melhorias']['type']);
        $this->assertSame('string', $schema['properties']['melhorias']['items']['type']);
        $this->assertSame('string', $schema['properties']['observacoes']['type']);
    }

    /**
     * Lista os estilos textuais habilitados para conferir a interpolação.
     *
     * @return array<string, array{string}> Casos de teste identificados pelo estilo.
     */
    public static function textStyles(): array
    {
        $cases = [];
        foreach (TextoAgent::styles() as $style) {
            $cases[$style] = [$style];
        }

        return $cases;
    }

    /**
     * Fornece uma resposta estruturada válida para os testes do agente de elaboração de texto.
     *
     * @return array{
     *     texto_elaborado: string,
     *     estilo_aplicado: string,
     *     assunto_sugerido: string,
     *     melhorias: list<string>,
     *     observacoes: string
     * } Resultado válido segundo o contrato de TextoAgent.
     */
    private static function textoResult(): array
    {
        return [
            'texto_elaborado' => "Prezado(a) [nome do destinatário],\n\nSolicitamos a prorrogação do prazo de entrega do relatório técnico.",
            'estilo_aplicado' => 'Comunicado / E-mail Institucional',
            'assunto_sugerido' => 'Solicitação de prorrogação de prazo para entrega de relatório',
            'melhorias' => [
                'Adequação à linguagem formal institucional.',
                'Estruturação com vocativo e parágrafo objetivo.',
            ],
            'observacoes' => 'Preencha [nome do destinatário] antes do envio.',
        ];
    }
}

