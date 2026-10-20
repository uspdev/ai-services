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
        $definitions = [
            [
                'slug' => BibliografiaAgent::SLUG,
                'version' => 1,
                'nome' => 'Formatador de Referências Bibliográficas',
                'descricao' => 'Normaliza e valida listas de referências bibliográficas segundo normas acadêmicas.',
                'system_prompt' => BibliografiaAgent::defaultTemplate(),
                'changelog' => 'Versão inicial do prompt de formatação bibliográfica.',
                'metadata' => [
                    'standards' => BibliografiaAgent::DEFAULT_STANDARDS,
                ],
            ],
            [
                'slug' => EmentaAgent::SLUG,
                'version' => 1,
                'nome' => 'Revisor de Ementas e Conteúdo Pedagógico',
                'descricao' => 'Avalia, traduz e revisa ementas, objetivos e conteúdo programático em português e inglês.',
                'system_prompt' => EmentaAgent::defaultTemplate(),
                'changelog' => 'Versão inicial do prompt de revisão de ementas.',
                'metadata' => null,
            ],
            [
                'slug' => TextoAgent::SLUG,
                'version' => 1,
                'nome' => 'Assistente de Elaboração de Textos',
                'descricao' => 'Elabora e aprimora textos institucionais e acadêmicos a partir de rascunhos do usuário.',
                'system_prompt' => TextoAgent::defaultTemplate(),
                'changelog' => 'Versão inicial do prompt de elaboração de textos.',
                'metadata' => [
                    'styles' => TextoAgent::DEFAULT_STYLES,
                ],
            ],
        ];

        foreach ($definitions as $definition) {
            $slug = $definition['slug'];
            $hasActiveVersion = AiPrompt::forSlug($slug)->active()->exists();

            AiPrompt::firstOrCreate(
                [
                    'slug' => $slug,
                    'version' => $definition['version'],
                ],
                [
                    'status' => $hasActiveVersion ? PromptStatus::Archived : PromptStatus::Active,
                    'nome' => $definition['nome'],
                    'descricao' => $definition['descricao'],
                    'system_prompt' => $definition['system_prompt'],
                    'changelog' => $definition['changelog'],
                    'metadata' => $definition['metadata'],
                    'published_at' => $hasActiveVersion ? null : now(),
                ]
            );

            AiPrompt::forgetCache($slug);
        }
    }
}
