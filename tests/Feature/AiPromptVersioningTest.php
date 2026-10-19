<?php

namespace Tests\Feature;

use App\Ai\Agents\BibliografiaAgent;
use App\Ai\Agents\EmentaAgent;
use App\Enums\PromptStatus;
use App\Models\AiPrompt;
use Database\Seeders\AiPromptSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Verifica o ciclo de vida de prompts em tabela única (`draft`, `active`, `archived`),
 * o cache da versão ativa, o seeder inicial e o fallback para definições em código.
 */
class AiPromptVersioningTest extends TestCase
{
    /**
     * Configura um banco SQLite em memória isolado e cria a tabela `ai_prompts`.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
        ]);

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
    }

    /**
     * Confere que o fallback em código funciona quando a tabela ainda está vazia.
     *
     * @return void
     */
    public function test_fallback_returns_default_prompts_when_database_is_empty(): void
    {
        $this->assertSame(0, AiPrompt::count());

        $bibliografiaPrompt = (new BibliografiaAgent('APA 7th Edition'))->instructions();
        $ementaPrompt = (new EmentaAgent)->instructions();

        $this->assertStringContainsString('following the APA 7th Edition standard', $bibliografiaPrompt);
        $this->assertStringContainsString('Academic and Curriculum Reviewer', $ementaPrompt);
        $this->assertSame(BibliografiaAgent::DEFAULT_STANDARDS, BibliografiaAgent::standards());
    }

    /**
     * Confere que o seeder popula a versão 1 ativa de bibliografia e ementa de forma idempotente.
     *
     * @return void
     */
    public function test_seeder_populates_initial_active_versions_idempotently(): void
    {
        $this->seed(AiPromptSeeder::class);
        $this->seed(AiPromptSeeder::class);

        $this->assertSame(2, AiPrompt::count());

        $bibliografia = AiPrompt::findActive('bibliografia');
        $ementa = AiPrompt::findActive('ementa');

        $this->assertNotNull($bibliografia);
        $this->assertSame(1, $bibliografia->version);
        $this->assertSame(PromptStatus::Active, $bibliografia->status);
        $this->assertNotNull($bibliografia->published_at);

        $this->assertNotNull($ementa);
        $this->assertSame(1, $ementa->version);
        $this->assertSame(PromptStatus::Active, $ementa->status);
        $this->assertSame('Ativa (Produção)', $ementa->status->label());
    }

    /**
     * Confere o fluxo completo: criação de rascunho futuro (`draft`), teste isolado da versão,
     * ativação em produção (arquivando a anterior) e rollback para versão passada (`archived`).
     *
     * @return void
     */
    public function test_lifecycle_transitions_between_draft_active_and_archived_with_cache_invalidation(): void
    {
        $this->seed(AiPromptSeeder::class);

        $v1 = AiPrompt::findActive('ementa');
        $this->assertNotNull($v1);

        // Popula o cache da versão ativa (v1).
        $this->assertSame($v1->system_prompt, AiPrompt::systemPrompt('ementa'));
        $this->assertTrue(Cache::has(AiPrompt::cacheKey('ementa')));

        // 1. Cria uma versão futura (v2) em status 'draft'.
        $v2 = $v1->createNextDraft('Inclui diretriz extra para cursos interdisciplinares.');
        $v2->update(['system_prompt' => 'Prompt de Ementa v2 (Rascunho em Homologação)']);

        $this->assertSame(2, $v2->version);
        $this->assertSame(PromptStatus::Draft, $v2->status);
        $this->assertSame('Rascunho (Futura)', $v2->status->label());

        // Enquanto v2 é 'draft', a produção continua recebendo v1.
        $this->assertSame($v1->system_prompt, (new EmentaAgent)->instructions());

        // Mas a v2 pode ser testada explicitamente informando o número da versão.
        $this->assertSame(
            'Prompt de Ementa v2 (Rascunho em Homologação)',
            (new EmentaAgent(version: 2))->instructions()
        );

        // 2. Promove a v2 para 'active': v1 deve passar para 'archived' e o cache deve ser limpo.
        $v2->activate();

        $v1->refresh();
        $v2->refresh();

        $this->assertSame(PromptStatus::Archived, $v1->status);
        $this->assertSame('Arquivada (Passada)', $v1->status->label());
        $this->assertSame(PromptStatus::Active, $v2->status);
        $this->assertNotNull($v2->published_at);

        // A chamada padrão agora retorna imediatamente a v2.
        $this->assertSame('Prompt de Ementa v2 (Rascunho em Homologação)', (new EmentaAgent)->instructions());

        // 3. Rollback: reativar a v1 arquiva a v2 e restaura a v1 em produção.
        $v1->activate();

        $v1->refresh();
        $v2->refresh();

        $this->assertSame(PromptStatus::Active, $v1->status);
        $this->assertSame(PromptStatus::Archived, $v2->status);
        $this->assertSame($v1->system_prompt, (new EmentaAgent)->instructions());
    }

    /**
     * Confere interpolação de parâmetros e customização de metadados em versões de bibliografia.
     *
     * @return void
     */
    public function test_bibliografia_version_supports_parameter_interpolation_and_custom_metadata(): void
    {
        $this->seed(AiPromptSeeder::class);

        $v1 = AiPrompt::findActive('bibliografia');
        $v2 = $v1->createNextDraft('Adiciona norma Chicago e template simplificado.');
        $v2->update([
            'system_prompt' => 'Format references using {{standard}} rules (v2).',
            'metadata' => [
                'standards' => ['ABNT NBR 6023:2025', 'Chicago 17th Edition'],
            ],
        ]);

        // Testa a v2 ainda em draft via BibliografiaAgent.
        $draftAgent = new BibliografiaAgent('Chicago 17th Edition', version: 2);
        $this->assertSame('Format references using Chicago 17th Edition rules (v2).', $draftAgent->instructions());

        // Após ativar v2, a lista de normas reflete os metadados da versão ativa.
        $v2->activate();
        $this->assertSame(['ABNT NBR 6023:2025', 'Chicago 17th Edition'], BibliografiaAgent::standards());
    }

    /**
     * Confere que solicitar uma versão inexistente lança InvalidArgumentException.
     *
     * @return void
     */
    public function test_requesting_nonexistent_version_throws_invalid_argument_exception(): void
    {
        $this->seed(AiPromptSeeder::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Versão 99 do prompt 'ementa' não encontrada.");

        AiPrompt::systemPrompt('ementa', version: 99);
    }
}

