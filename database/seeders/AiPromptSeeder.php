<?php

namespace Database\Seeders;

use App\Ai\Agents\BibliografiaAgent;
use App\Ai\Agents\EmentaAgent;
use App\Ai\Agents\TextoAgent;
use App\Enums\PromptStatus;
use App\Models\AiPrompt;
use Illuminate\Database\Seeder;

/**
 * Popula a tabela `ai_prompts` com a versão inicial (v1) dos prompts padrão dos agentes.
 */
class AiPromptSeeder extends Seeder
{
    /**
     * Registra de forma idempotente as versões iniciais de `bibliografia` e `ementa`.
     *
     * Caso o serviço ainda não possua nenhuma versão ativa no banco, a `v1` é criada
     * como `active`; caso uma versão superior já esteja ativa, a `v1` entra como `archived`.
     *
     * @return void
     */
    public function run(): void
    {
        AiPrompt::ensureDefaultsExist();
    }
}
