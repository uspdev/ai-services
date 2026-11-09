<?php

namespace Tests\Feature;

use App\Enums\PromptStatus;
use App\Models\AiPrompt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Verifica o gerenciamento web de prompts (listagem, edição, criação de versão derivada e ativação)
 * restrito à permissão `can:manager`.
 */
class AiPromptCrudTest extends TestCase
{
    /** @var bool Controla se o usuário autenticado no teste possui a permissão `manager`. */
    private bool $isManager = true;

    /** @var string Caminho temporário do arquivo de log de prompts utilizado durante cada teste. */
    private string $tempLogPath;

    /**
     * Configura o banco SQLite em memória, cria a tabela `ai_prompts` e registra o Gate `manager`.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tempLogPath = sys_get_temp_dir() . '/aiprompt_test_' . uniqid('', true) . '.log';

        $rightMenu = [
            [
                'text' => '<i class="fas fa-cog"></i> Settings',
                'can' => 'manager',
                'submenu' => [
                    [
                        'text' => '<i class="fas fa-sliders-h"></i> Prompts de IA',
                        'url' => 'settings/prompts',
                        'can' => 'manager',
                    ],
                ],
            ],
        ];

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'aiService.promptLogPath' => $this->tempLogPath,
            'laravel-usp-theme.menu' => [],
            'laravel-usp-theme.right_menu' => $rightMenu,
            'laravel-usp-theme.mensagensFlash' => true,
        ]);

        View::share('menu', []);
        View::share('right_menu', $rightMenu);
        URL::forceRootUrl('http://localhost');

        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default(PromptStatus::Draft->value);
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->longText('system_prompt');
            $table->text('changelog')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'version']);
            $table->index(['slug', 'status']);
        });

        Gate::define('manager', fn (): bool => $this->isManager);
        Gate::define('user', fn (): bool => true);

        // Utiliza a classe base AuthenticatableUser para delegar a autorização diretamente ao Gate sem exigir tabelas do Spatie Permission.
        $this->actingAs((new AuthenticatableUser)->forceFill([
            'id' => 1,
            'codpes' => 123456,
            'name' => 'Gerente Teste',
            'email' => 'gerente@example.com',
        ]));
    }

    /**
     * Remove o arquivo temporário de log após cada teste.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (isset($this->tempLogPath) && is_file($this->tempLogPath)) {
            @unlink($this->tempLogPath);
        }

        parent::tearDown();
    }

    /**
     * Confere que usuários sem a permissão `can:manager` recebem HTTP 403 nas rotas de prompts.
     *
     * @return void
     */
    public function test_routes_require_manager_permission(): void
    {
        AiPrompt::ensureDefaultsExist();
        $prompt = AiPrompt::forSlug('bibliografia')->firstOrFail();

        $this->isManager = false;

        $this->get(route('prompts.index'))->assertForbidden();
        $this->get(route('prompts.edit', $prompt))->assertForbidden();
        $this->put(route('prompts.update', $prompt), [])->assertForbidden();
        $this->patch(route('prompts.activate', $prompt))->assertForbidden();
    }

    /**
     * Confere que a listagem inicializa os registros padrão quando o banco está vazio e exibe a tela de edição.
     *
     * @return void
     */
    public function test_index_initializes_defaults_and_edit_displays_diff_and_placeholders(): void
    {
        $this->assertSame(0, AiPrompt::count());

        $this->get(route('prompts.index'))
            ->assertOk()
            ->assertSee('nav-link dropdown-toggle active', false)
            ->assertSee('dropdown-item active', false)
            ->assertSee('Gerenciamento de Prompts de IA')
            ->assertSee('Formatador de Referências Bibliográficas')
            ->assertSee('Revisor de Ementas e Conteúdo Pedagógico')
            ->assertSee('Assistente de Elaboração de Textos');

        $this->assertSame(3, AiPrompt::count());

        $bibliografia = AiPrompt::forSlug('bibliografia')->firstOrFail();

        $this->get(route('prompts.edit', $bibliografia))
            ->assertOk()
            ->assertSee('nav-link dropdown-toggle active', false)
            ->assertSee('dropdown-item active', false)
            ->assertSee('Editar Prompt: Formatador de Referências Bibliográficas')
            ->assertSee('Restaurar padrão do código')
            ->assertSee('Comparador Diff')
            ->assertSee('{$standard}');
    }

    /**
     * Confere a atualização da própria versão (`save_mode = update`) incluindo a lista de opções em `metadata`.
     *
     * @return void
     */
    public function test_update_in_place_modifies_current_version_and_metadata_items(): void
    {
        AiPrompt::ensureDefaultsExist();
        $bibliografia = AiPrompt::forSlug('bibliografia')->firstOrFail();

        // Simula um registro antigo no log (14 meses atrás) e confere que o filtro de 12 meses o descarta.
        $oldEntry = [
            'slug' => 'bibliografia',
            'timestamp' => now()->subMonths(14)->toIso8601String(),
            'formatted_date' => now()->subMonths(14)->format('d/m/Y H:i:s'),
            'user_id' => 99,
            'user_name' => 'Usuário Antigo',
            'user_codpes' => 999999,
            'version' => 1,
            'action' => 'update',
            'action_label' => 'Versão atualizada',
            'details' => 'Alteração antiga fora da janela de 12 meses.',
        ];
        file_put_contents(
            $this->tempLogPath,
            '[2025-01-01 00:00:00] testing.INFO: AiPrompt [bibliografia v1]: Versão atualizada ' . json_encode($oldEntry) . "\n"
        );

        $this->put(route('prompts.update', $bibliografia), [
            'nome' => 'Bibliografia Customizada',
            'descricao' => 'Descrição atualizada.',
            'system_prompt' => 'Prompt customizado para {$standard}.',
            'changelog' => 'Ajuste direto na v1.',
            'metadata_items' => "ABNT NBR 6023:2025\nChicago 17th Edition\n",
            'save_mode' => 'update',
            'activate_now' => '1',
        ])->assertRedirect(route('prompts.index'));

        $this->assertSame(1, AiPrompt::forSlug('bibliografia')->count());

        $bibliografia->refresh();
        $this->assertSame('Bibliografia Customizada', $bibliografia->nome);
        $this->assertSame('Prompt customizado para {$standard}.', $bibliografia->system_prompt);
        $this->assertSame(['ABNT NBR 6023:2025', 'Chicago 17th Edition'], $bibliografia->metadata['standards']);
        $this->assertSame(PromptStatus::Active, $bibliografia->status);

        $logs = AiPrompt::logsForSlug('bibliografia');
        $this->assertCount(1, $logs);
        $this->assertSame('Gerente Teste', $logs[0]['user_name']);
        $this->assertSame('update', $logs[0]['action']);
        $this->assertStringContainsString('system_prompt', $logs[0]['details']);

        $this->get(route('prompts.index'))
            ->assertOk()
            ->assertSee('Logs (1)')
            ->assertSee('123456 | Gerente Teste')
            ->assertSee('Versão atualizada');
    }

    /**
     * Confere a criação de uma nova versão (`save_mode = new_version`) com ativação imediata e rollback posterior.
     *
     * @return void
     */
    public function test_update_as_new_version_creates_v2_and_allows_switching_active_version(): void
    {
        AiPrompt::ensureDefaultsExist();
        $v1 = AiPrompt::forSlug('texto')->firstOrFail();

        $this->put(route('prompts.update', $v1), [
            'nome' => 'Assistente de Elaboração de Textos',
            'descricao' => 'Versão evoluída.',
            'system_prompt' => 'Novo prompt de texto v2 com {$style} e {$availableStyles}.',
            'changelog' => 'Criação da v2 pelo painel.',
            'metadata_items' => "Automático (detectar pelo rascunho)\nComunicado Interno\nOfício",
            'save_mode' => 'new_version',
            'activate_now' => '1',
        ])->assertRedirect(route('prompts.index'));

        $v1->refresh();
        $v2 = AiPrompt::forSlug('texto')->where('version', 2)->firstOrFail();

        $this->assertSame(PromptStatus::Archived, $v1->status);
        $this->assertSame(PromptStatus::Active, $v2->status);
        $this->assertSame('Novo prompt de texto v2 com Comunicado Interno e Comunicado Interno, Ofício.', AiPrompt::systemPrompt('texto', ['style' => 'Comunicado Interno']));

        // Faz rollback ativando novamente a v1 pela rota PATCH.
        $this->patch(route('prompts.activate', $v1))
            ->assertRedirect(route('prompts.index'));

        $v1->refresh();
        $v2->refresh();

        $this->assertSame(PromptStatus::Active, $v1->status);
        $this->assertSame(PromptStatus::Archived, $v2->status);

        $logs = AiPrompt::logsForSlug('texto');
        $this->assertCount(2, $logs);
        $this->assertSame('Gerente Teste', $logs[0]['user_name']);
        $this->assertNotEmpty($logs[0]['formatted_date']);
    }
}
