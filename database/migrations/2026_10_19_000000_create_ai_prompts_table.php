<?php

use App\Enums\PromptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela única de prompts de IA com controle de versão e ciclo de vida (status).
 */
return new class extends Migration
{
    /**
     * Executa a criação da tabela `ai_prompts` e de seus índices de busca e unicidade.
     *
     * @return void
     */
    public function up(): void
    {
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

            // Impede numeração de versão duplicada para o mesmo serviço (slug).
            $table->unique(['slug', 'version']);

            // Otimiza a consulta da versão ativa em produção por serviço.
            $table->index(['slug', 'status']);
        });
    }

    /**
     * Reverte a criação da tabela `ai_prompts`.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};

