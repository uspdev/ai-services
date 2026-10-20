<?php

namespace Tests\Feature;

use App\Models\AiPrompt;
use App\Models\User;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifica contratos web/JSON, autenticação e apresentação das falhas de execução de IA.
 *
 * Usa usuário e cache em memória, HTTP simulado e logs interceptados.
 */
class AiControllerTest extends TestCase
{
    /**
     * Configura o ambiente isolado para testar as rotas e renderizar as telas reais.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.debug' => true,
            'ai.default' => 'litellm',
            'ai.providers.litellm' => [
                'driver' => 'openai-compatible',
                'url' => 'https://litellm.example.com/v1',
                'key' => 'sdk-test-key',
                'models' => ['text' => ['default' => 'sdk-test-model']],
            ],
            'cache.default' => 'array',
            // Os menus do ambiente podem consultar permissões no banco local.
            'laravel-usp-theme.menu' => [],
            'laravel-usp-theme.right_menu' => [],
            'laravel-usp-theme.mensagensFlash' => true,
        ]);
        // O tema compartilha os menus no boot, antes das configurações deste teste.
        View::share('menu', []);
        View::share('right_menu', []);
        URL::forceRootUrl('http://localhost');
        Http::preventStrayRequests();
        Log::spy();
        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Teste', 'email' => 'teste@example.com']));
    }

    /**
     * Confere o status e o envelope de falha sem expor detalhes do provider, mesmo em debug.
     *
     * @param string $route Nome da rota autenticada a testar.
     * @param int $upstreamStatus Status HTTP simulado do provider.
     * @param int $expectedStatus Status esperado na resposta da aplicação.
     * @return void
     */
    #[DataProvider('providerFailures')]
    public function test_json_provider_failures_are_explicit_and_sanitized(string $route, int $upstreamStatus, int $expectedStatus): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'private-upstream-details']], $upstreamStatus)]);

        $response = $this->postJson(route($route), self::formInput($route));

        $response->assertStatus($expectedStatus)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message']);
        $this->assertSame(['success', 'message'], array_keys($response->json()));
        $this->assertStringNotContainsString('private-upstream-details', $response->getContent());
        $this->assertStringNotContainsString('sdk-test-key', $response->getContent());
    }

    /**
     * Fornece falhas HTTP dos dois fluxos e seus status públicos correspondentes.
     *
     * @return array<string, array{string, int, int}> Rota, status do provider e status público.
     */
    public static function providerFailures(): array
    {
        $cases = [];
        foreach (['bibliografia.index', 'ementa.index', 'texto.index'] as $route) {
            foreach ([429 => 429, 503 => 503, 402 => 503, 403 => 502, 500 => 502, 408 => 504, 504 => 504] as $upstream => $expected) {
                $cases["{$route}: HTTP {$upstream}"] = [$route, $upstream, $expected];
            }
        }

        return $cases;
    }

    /**
     * Confere erro de conexão e timeout cURL encapsulados pelo SDK.
     *
     * @param string $route Nome da rota autenticada a testar.
     * @param bool $timeout Indica se a falha simulada é um timeout em vez de DNS/conexão.
     * @return void
     */
    #[DataProvider('connectionFailures')]
    public function test_json_connection_failures_and_timeouts(string $route, bool $timeout): void
    {
        Http::fake(function (Request $request) use ($timeout) {
            // Reproduz a causa que o cliente HTTP e o SDK envolvem em suas exceções.
            return Create::rejectionFor(new ConnectException(
                'private-connection-details',
                $request->toPsrRequest(),
                null,
                ['errno' => $timeout ? 28 : 6],
            ));
        });

        $response = $this->postJson(route($route), self::formInput($route));

        $response->assertStatus($timeout ? 504 : 503)->assertJsonPath('success', false);
        $this->assertStringNotContainsString('private-connection-details', $response->getContent());
    }

    /**
     * Fornece cenários de conexão e timeout nos dois fluxos.
     *
     * @return array<string, array{string, bool}> Rota e indicador de timeout.
     */
    public static function connectionFailures(): array
    {
        return [
            'bibliography connection' => ['bibliografia.index', false],
            'bibliography timeout' => ['bibliografia.index', true],
            'syllabus connection' => ['ementa.index', false],
            'syllabus timeout' => ['ementa.index', true],
            'text connection' => ['texto.index', false],
            'text timeout' => ['texto.index', true],
        ];
    }

    /**
     * Confere a mensagem pública para JSON inválido ou campos fora do contrato esperado.
     *
     * @param string $route Nome da rota autenticada a testar.
     * @return void
     */
    #[DataProvider('aiRoutes')]
    public function test_invalid_model_output_returns_a_json_error(string $route): void
    {
        Http::fake(['*' => Http::response([
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['role' => 'assistant', 'content' => 'private-invalid-output'],
            ]],
        ])]);

        $response = $this->postJson(route($route), self::formInput($route));

        $response->assertStatus(502)->assertJsonPath('success', false);
        $this->assertStringContainsString('resposta inválida ou incompleta', $response->json('message'));
        $this->assertStringNotContainsString('private-invalid-output', $response->getContent());
    }

    /**
     * Confere erro de configuração sem enviar uma requisição ao gateway.
     *
     * @param string $route Nome da rota autenticada a testar.
     * @return void
     */
    #[DataProvider('aiRoutes')]
    public function test_missing_provider_configuration_returns_service_unavailable(string $route): void
    {
        config(['ai.providers.litellm.url' => null]);

        $this->postJson(route($route), self::formInput($route))
            ->assertStatus(503)
            ->assertJsonPath('success', false);
        Http::assertNothingSent();
    }

    /**
     * Confere o retorno ao formulário, a preservação da entrada e a exibição do alerta do tema.
     *
     * @param string $route Nome da rota autenticada a testar.
     * @return void
     */
    #[DataProvider('aiRoutes')]
    public function test_web_failures_preserve_input_and_display_a_message(string $route): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'private-upstream-details']], 503)]);
        $input = self::formInput($route);

        $response = $this->post(route($route), [...$input, 'unrelated' => 'private-unrelated-input']);

        $response->assertRedirect(route($route))->assertSessionHasErrors('ai');
        foreach ($input as $field => $value) {
            $response->assertSessionHas("_old_input.{$field}", $value);
        }
        $response->assertSessionMissing('_old_input.unrelated');
        $this->get(route($route))
            ->assertOk()
            ->assertSee('O serviço de IA está indisponível no momento.')
            ->assertSee($input['message'] ?? $input['mensagem'] ?? $input['rascunho'])
            ->assertDontSee('private-upstream-details');
    }

    /**
     * Confere que entrada inválida mantém o contrato de validação do Laravel e não chama IA.
     *
     * @param string $route Nome da rota autenticada a testar.
     * @return void
     */
    #[DataProvider('aiRoutes')]
    public function test_invalid_user_input_is_rejected_before_calling_ai(string $route): void
    {
        $field = match ($route) {
            'bibliografia.index' => 'message',
            'ementa.index' => 'mensagem',
            default => 'rascunho',
        };

        $this->postJson(route($route), [])->assertUnprocessable()->assertJsonValidationErrors($field);
        Http::assertNothingSent();
    }

    /**
     * Confere a obrigatoriedade de autenticação nos dois endpoints.
     *
     * @param string $route Nome da rota autenticada a testar.
     * @return void
     */
    #[DataProvider('aiRoutes')]
    public function test_ai_routes_require_authentication(string $route): void
    {
        auth()->forgetGuards();

        $this->postJson(route($route), self::formInput($route))->assertUnauthorized();
        Http::assertNothingSent();
    }

    /**
     * Confere os dados esperados pelas telas e pela API, inclusive a leitura do cache.
     *
     * @param string $route Nome da rota autenticada a testar.
     * @return void
     */
    #[DataProvider('aiRoutes')]
    public function test_successful_results_are_presented_in_web_and_json(string $route): void
    {
        $result = self::resultFor($route);
        Http::fake(['*' => Http::response([
            'model' => 'backend-model',
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['role' => 'assistant', 'content' => json_encode($result)],
            ]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10],
        ])]);
        $input = self::formInput($route);

        $web = $this->post(route($route), $input);
        $web->assertOk()->assertSee('Resultados')->assertSee('30 tokens');
        $json = $this->postJson(route($route), $input);
        $json->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.estatisticas.cached', true);

        if ($route === 'bibliografia.index') {
            $web->assertSee($result['referencias'][0])
                ->assertSee('Dedalus (EESC)');
            $json->assertJsonPath('data.respostas', $result['referencias'])
                ->assertJsonPath('data.dedalus.0.query', 'Título')
                ->assertJsonPath('data.dedalus.0.library', 'EESC')
                ->assertJsonPath('data.standard', $input['standard'])
                ->assertJsonPath('data.prompt', AiPrompt::systemPrompt('bibliografia', ['standard' => $input['standard']]));
        } elseif ($route === 'ementa.index') {
            $web->assertSee('Score: 90/100')->assertSee('Status: Ausente');
            // Texto com marcação deve ser apresentado literalmente, mantendo as quebras de linha.
            $web->assertSee(nl2br(e($result['texto_revisado']['ementa']['pt'])), false)
                ->assertDontSee('<script>alert("model-output")</script>', false);
            $json->assertJsonPath('data.resposta', $result['texto_revisado'])
                ->assertJsonPath('data.analiseIngles', $result['explicacoes']['en'])
                ->assertJsonPath('data.prompt', AiPrompt::systemPrompt('ementa'));
        } else {
            $web->assertSee(nl2br(e($result['texto_elaborado'])), false)
                ->assertSee($result['estilo_aplicado'])
                ->assertSee($result['assunto_sugerido'])
                ->assertDontSee('<script>alert("model-output")</script>', false);
            $json->assertJsonPath('data.textoElaborado', $result['texto_elaborado'])
                ->assertJsonPath('data.estiloAplicado', $result['estilo_aplicado'])
                ->assertJsonPath('data.assuntoSugerido', $result['assunto_sugerido'])
                ->assertJsonPath('data.melhorias', $result['melhorias'])
                ->assertJsonPath('data.observacoes', $result['observacoes'])
                ->assertJsonPath('data.style', $input['style'])
                ->assertJsonPath('data.prompt', AiPrompt::systemPrompt('texto', ['style' => $input['style']]));
        }
        Http::assertSentCount(1);
    }

    /**
     * Confere as instruções de GET JSON e seus nomes de campos reais.
     *
     * @return void
     */
    public function test_json_get_describes_the_actual_input_fields(): void
    {
        $this->getJson(route('bibliografia.index'))->assertOk()
            ->assertJsonPath('message', 'Envie uma requisição POST com os campos "message" e "standard" para processar.');
        $this->getJson(route('ementa.index'))->assertOk()
            ->assertJsonPath('mensagem', 'Envie uma requisição POST com o campo "mensagem" para processar.');
        $this->getJson(route('texto.index'))->assertOk()
            ->assertJsonPath('message', 'Envie uma requisição POST com os campos "rascunho" e "style" para processar.');
        Http::assertNothingSent();
    }

    /**
     * Lista as rotas dos serviços para repetir os cenários de integração.
     *
     * @return array<string, array{string}> Rotas autenticadas dos formulários.
     */
    public static function aiRoutes(): array
    {
        return [
            'bibliography' => ['bibliografia.index'],
            'syllabus' => ['ementa.index'],
            'text' => ['texto.index'],
        ];
    }

    /**
     * Monta a entrada pública do formulário correspondente à rota.
     *
     * @param string $route Nome da rota do formulário.
     * @return array<string, string> Campos necessários para uma entrada válida.
     */
    private static function formInput(string $route): array
    {
        return match ($route) {
            'bibliografia.index' => ['message' => 'Referência original.', 'standard' => 'APA 7th Edition'],
            'ementa.index' => ['mensagem' => 'Ementa original para revisão.'],
            default => [
                'rascunho' => 'Rascunho original para elaboração.',
                'style' => 'Automático (detectar pelo rascunho)',
                'contexto' => 'Comunicado interno.',
            ],
        };
    }

    /**
     * Monta uma resposta válida com os campos consumidos pelas telas atuais.
     *
     * @param string $route Nome da rota que determina o contrato de saída.
     * @return array<string, mixed> Resultado simulado de bibliografia, ementa ou texto.
     */
    private static function resultFor(string $route): array
    {
        if ($route === 'bibliografia.index') {
            return [
                'referencias' => ['Referência revisada.'],
                'explicacoes' => ['Pontuação revisada.'],
                'busca_dedalus' => [['autor' => 'Autor', 'titulo' => 'Título']],
                'confiancas' => [90],
            ];
        }

        if ($route === 'texto.index') {
            return [
                'texto_elaborado' => "Comunicado revisado.\n<script>alert(\"model-output\")</script>",
                'estilo_aplicado' => 'Comunicado / E-mail Institucional',
                'assunto_sugerido' => 'Comunicado sobre prazos',
                'melhorias' => ['Estruturação formal aplicada.'],
                'observacoes' => 'Confirme a data limite.',
            ];
        }

        return [
            'score' => ['valor' => 90, 'justificativa' => 'Boa qualidade.'],
            'explicacoes' => [
                'pt' => [
                    'gramatica_acentuacao_pontuacao' => 'Pontuação revisada.',
                    'redacao_academica_clareza_pedagogica' => 'Texto claro.',
                    'terminologia_tecnica' => 'Terminologia consistente.',
                ],
                'en' => [
                    'status' => 'ausente',
                    'gramatica_acentuacao_pontuacao' => 'Tradução criada.',
                    'redacao_academica_clareza_pedagogica' => 'Texto claro.',
                    'terminologia_tecnica' => 'Terminologia consistente.',
                ],
            ],
            'texto_revisado' => [
                'ementa' => ['pt' => "Introdução.\n<script>alert(\"model-output\")</script>", 'en' => 'Introduction.'],
                'objetivos' => ['pt' => 'Desenvolver algoritmos.', 'en' => 'Develop algorithms.'],
                'conteudo_programatico' => ['pt' => 'Variáveis.', 'en' => 'Variables.'],
            ],
        ];
    }
}
