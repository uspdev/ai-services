<?php

namespace App\Models;

use App\Ai\Agents\BibliografiaAgent;
use App\Ai\Agents\EmentaAgent;
use App\Ai\Agents\TextoAgent;
use App\Enums\PromptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use PDOException;

/**
 * Representa uma versão de prompt de IA armazenada no banco de dados.
 *
 * Utiliza uma tabela única (`ai_prompts`) controlada por `slug`, `version` e `status`
 * (`draft`, `active`, `archived`). Mantém o modelo agnóstico de regras de negócio
 * específicas de cada agente, cuidando apenas de persistência, cache e interpolação.
 */
class AiPrompt extends Model
{
    /** @var string Nome da tabela associada ao modelo. */
    protected $table = 'ai_prompts';

    /** @var list<string> Atributos atribuíveis em massa. */
    protected $fillable = [
        'slug',
        'version',
        'status',
        'nome',
        'descricao',
        'system_prompt',
        'changelog',
        'metadata',
        'published_at',
    ];

    /**
     * Define as conversões de tipos dos atributos do modelo.
     *
     * @return array<string, string> Mapa de atributos e seus respectivos casts.
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => PromptStatus::class,
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Filtra a consulta pelo identificador do serviço (`slug`).
     *
     * @param Builder<self> $query Construtor da consulta Eloquent.
     * @param string $slug Identificador lógico do serviço (ex.: 'bibliografia', 'ementa').
     * @return Builder<self> Consulta filtrada pelo slug.
     */
    public function scopeForSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    /**
     * Filtra apenas versões com status ativo em produção (`active`).
     *
     * @param Builder<self> $query Construtor da consulta Eloquent.
     * @return Builder<self> Consulta filtrada por status ativo.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PromptStatus::Active);
    }

    /**
     * Filtra apenas versões em rascunho/futuras (`draft`).
     *
     * @param Builder<self> $query Construtor da consulta Eloquent.
     * @return Builder<self> Consulta filtrada por status rascunho.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PromptStatus::Draft);
    }

    /**
     * Filtra apenas versões passadas/arquivadas (`archived`).
     *
     * @param Builder<self> $query Construtor da consulta Eloquent.
     * @return Builder<self> Consulta filtrada por status arquivado.
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', PromptStatus::Archived);
    }

    /**
     * Promove esta versão para `active` e arquiva a versão ativa anterior do mesmo `slug`.
     *
     * Executa a transição em transação para garantir que apenas uma versão permaneça
     * ativa por `slug` e invalida o cache do prompt ativo logo em seguida.
     *
     * @return self A própria instância atualizada como ativa.
     */
    public function activate(): self
    {
        DB::transaction(function () {
            // Rebaixa qualquer outra versão atualmente ativa deste serviço para 'archived'.
            static::forSlug($this->slug)
                ->active()
                ->where('id', '!=', $this->id)
                ->update(['status' => PromptStatus::Archived]);

            $this->update([
                'status' => PromptStatus::Active,
                'published_at' => now(),
            ]);
        });

        self::forgetCache($this->slug);

        return $this;
    }

    /**
     * Cria uma nova versão futura (`draft`) copiando os dados desta versão como ponto de partida.
     *
     * @param string|null $changelog Descrição opcional das alterações planejadas para a nova versão.
     * @return self Nova instância criada com `version` incrementada e status `draft`.
     */
    public function createNextDraft(?string $changelog = null): self
    {
        $maxVersion = (int) static::forSlug($this->slug)->max('version');

        return static::create([
            'slug' => $this->slug,
            'version' => $maxVersion + 1,
            'status' => PromptStatus::Draft,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'system_prompt' => $this->system_prompt,
            'metadata' => $this->metadata,
            'changelog' => $changelog,
        ]);
    }

    /**
     * Retorna o caminho do arquivo de log dedicado às alterações de prompts.
     *
     * @return string Caminho absoluto para `storage/logs/aiprompt.log` (ou caminho configurado).
     */
    public static function logFilePath(): string
    {
        return (string) config('aiService.promptLogPath', storage_path('logs/aiprompt.log'));
    }

    /**
     * Retorna o período em meses configurado para exibição do histórico de logs de prompts.
     *
     * @return int Quantidade de meses (padrão: 12).
     */
    public static function logRetentionMonths(): int
    {
        return max(1, (int) config('aiService.promptLogRetentionMonths', 12));
    }

    /**
     * Registra uma entrada de auditoria em `storage/logs/aiprompt.log`.
     *
     * @param string $action Código da operação (`update`, `new_version`, `activate`).
     * @param string $actionLabel Rótulo legível da operação realizada.
     * @param string $details Descrição detalhada da alteração.
     * @param object|null $user Usuário autenticado responsável pela ação.
     * @return self A própria instância do prompt.
     */
    public function recordLog(string $action, string $actionLabel, string $details, ?object $user = null): self
    {
        $now = now();
        $entry = [
            'slug' => $this->slug,
            'timestamp' => $now->toIso8601String(),
            'formatted_date' => $now->format('d/m/Y H:i:s'),
            'user_id' => $user->id ?? null,
            'user_name' => $user->name ?? 'Sistema',
            'user_codpes' => $user->codpes ?? null,
            'user_email' => $user->email ?? null,
            'version' => $this->version,
            'action' => $action,
            'action_label' => $actionLabel,
            'details' => $details,
        ];

        $logPath = self::logFilePath();
        $dir = dirname($logPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $env = app()->environment();
        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line = sprintf(
            "[%s] %s.INFO: AiPrompt [%s v%d]: %s %s\n",
            $now->format('Y-m-d H:i:s'),
            $env,
            $this->slug,
            $this->version,
            $actionLabel,
            $json
        );

        file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);

        return $this;
    }

    /**
     * Retorna o histórico de alterações de um `slug` lido de `storage/logs/aiprompt.log`,
     * filtrado pelos últimos N meses configurados (`AI_PROMPT_LOG_RETENTION_MONTHS`) e
     * ordenado do mais recente ao mais antigo.
     *
     * @param string $slug Identificador do serviço.
     * @return list<array<string, mixed>> Lista ordenada de registros de log do prompt.
     */
    public static function logsForSlug(string $slug): array
    {
        $logPath = self::logFilePath();
        if (! is_file($logPath) || ! is_readable($logPath)) {
            return [];
        }

        $cutoff = now()->subMonths(self::logRetentionMonths());
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $entries = [];

        foreach ($lines as $line) {
            $jsonStart = strpos($line, '{');
            if ($jsonStart === false) {
                continue;
            }

            $decoded = json_decode(substr($line, $jsonStart), true);
            if (! is_array($decoded) || ($decoded['slug'] ?? null) !== $slug) {
                continue;
            }

            if (! empty($decoded['timestamp'])) {
                try {
                    if (Carbon::parse((string) $decoded['timestamp'])->lt($cutoff)) {
                        continue;
                    }
                } catch (\Throwable) {
                    // Mantém o registro caso o timestamp não possa ser interpretado.
                }
            }

            $entries[] = $decoded;
        }

        return array_reverse($entries);
    }

    /**
     * Interpola variáveis dinâmicas no `system_prompt` desta versão.
     *
     * @param array<string, mixed>|null $params Parâmetros para substituição no template.
     * @return string Texto final do prompt com os placeholders preenchidos.
     */
    public function interpolate(?array $params = []): string
    {
        return self::interpolateTemplate($this->system_prompt, $params);
    }

    /**
     * Retorna a chave de cache utilizada para armazenar a versão ativa de um `slug`.
     *
     * @param string $slug Identificador do serviço.
     * @return string Chave única de cache.
     */
    public static function cacheKey(string $slug): string
    {
        return "ai_prompt:{$slug}:active";
    }

    /**
     * Remove do cache a versão ativa armazenada para o `slug` informado.
     *
     * @param string $slug Identificador do serviço cujo cache deve ser invalidado.
     * @return void
     */
    public static function forgetCache(string $slug): void
    {
        Cache::forget(self::cacheKey($slug));
    }

    /**
     * Busca a versão ativa (`active`) de um prompt no banco de dados utilizando cache.
     *
     * Caso a aplicação ainda não esteja inicializada (ex.: DataProviders do PHPUnit),
     * o banco de dados não esteja disponível ou a tabela `ai_prompts` ainda não tenha
     * sido migrada/populada, retorna `null` sem armazenar falha em cache para permitir o fallback.
     *
     * @param string $slug Identificador do prompt.
     * @return self|null Instância ativa encontrada ou `null` quando inexistente/indisponível.
     */
    public static function findActive(string $slug): ?self
    {
        if (Facade::getFacadeApplication() === null || static::getConnectionResolver() === null) {
            return null;
        }

        $cacheKey = self::cacheKey($slug);
        $cached = Cache::get($cacheKey);
        if ($cached instanceof self) {
            return $cached;
        }

        try {
            $prompt = static::forSlug($slug)->active()->first();
        } catch (QueryException|PDOException) {
            // Permite que testes ou ambientes sem migration executem usando o fallback do agente.
            return null;
        }

        if ($prompt !== null) {
            Cache::put($cacheKey, $prompt, 3600);
        }

        return $prompt;
    }

    /**
     * Busca uma versão específica de um prompt no banco de dados (inclusive `draft` ou `archived`).
     *
     * @param string $slug Identificador do prompt.
     * @param int $version Número da versão desejada.
     * @return self|null Instância da versão solicitada ou `null` se não existir.
     */
    public static function findVersion(string $slug, int $version): ?self
    {
        if (static::getConnectionResolver() === null) {
            return null;
        }

        try {
            return static::forSlug($slug)->where('version', $version)->first();
        } catch (QueryException|PDOException) {
            return null;
        }
    }

    /**
     * Resolve o prompt de um serviço priorizando o banco de dados e recorrendo ao template de fallback.
     *
     * - Quando `$version` é informado, busca especificamente aquela versão no banco.
     * - Quando `$version` é `null`, busca a versão `active` em produção (com cache).
     * - Caso o banco ainda não possua versão ativa para o `$slug`, interpola `$fallbackTemplate`.
     *
     * @param string $slug Identificador do serviço no banco.
     * @param string|null $fallbackTemplate Template padrão do agente caso o banco esteja vazio.
     * @param array<string, mixed>|null $params Parâmetros para interpolação no template.
     * @param int|null $version Versão específica a recuperar (opcional; `null` usa a versão `active`).
     * @return string Instruções de sistema resolvidas e interpoladas.
     * @throws InvalidArgumentException Quando a versão solicitada ou o prompt não existir.
     */
    public static function resolve(
        string $slug,
        ?string $fallbackTemplate = null,
        ?array $params = [],
        ?int $version = null,
    ): string {
        if ($version !== null) {
            $specificVersion = self::findVersion($slug, $version);
            if ($specificVersion === null) {
                throw new InvalidArgumentException(
                    "Versão {$version} do prompt '{$slug}' não encontrada."
                );
            }

            return $specificVersion->interpolate($params);
        }

        $activePrompt = self::findActive($slug);
        if ($activePrompt !== null) {
            return $activePrompt->interpolate($params);
        }

        if ($fallbackTemplate === null) {
            throw new InvalidArgumentException(
                "Prompt '{$slug}' não encontrado."
            );
        }

        return self::interpolateTemplate($fallbackTemplate, $params);
    }

    /**
     * Interpola os parâmetros em um template de prompt mantendo compatibilidade de sintaxe.
     *
     * Suporta placeholders `{{chave}}` e `{$chave}` para substituição direta, além de
     * preservar a formatação `{{ $chave }}` presente no prompt inicial de bibliografia.
     *
     * @param string $template Texto bruto do prompt.
     * @param array<string, mixed>|null $params Parâmetros a interpolar.
     * @return string Texto com os parâmetros interpolados.
     */
    public static function interpolateTemplate(string $template, ?array $params = []): string
    {
        $standard = empty($params['standard']) ? 'ABNT NBR 6023:2025' : (string) $params['standard'];
        $availableStyles = empty($params['availableStyles'])
            ? TextoAgent::availableStylesList()
            : (string) $params['availableStyles'];
        $values = array_merge($params ?? [], [
            'standard' => $standard,
            'availableStyles' => $availableStyles,
        ]);

        foreach ($values as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $strVal = (string) $value;
            $template = str_replace(
                ["{\${$key}}", "{{{$key}}}", "{{ \${$key} }}"],
                [$strVal, $strVal, "{{ {$strVal} }}"],
                $template
            );
        }

        return $template;
    }

    /**
     * Retorna o template padrão em código associado a um `slug`.
     *
     * @param string $slug Identificador do serviço.
     * @return string|null Template padrão do agente ou `null` quando desconhecido.
     */
    public static function defaultTemplateForSlug(string $slug): ?string
    {
        return match ($slug) {
            BibliografiaAgent::SLUG => BibliografiaAgent::defaultTemplate(),
            EmentaAgent::SLUG => EmentaAgent::defaultTemplate(),
            TextoAgent::SLUG => TextoAgent::defaultTemplate(),
            default => null,
        };
    }

    /**
     * Retorna a chave de lista editável em `metadata` para o `slug` informado, se houver.
     *
     * @param string $slug Identificador do serviço.
     * @return string|null Nome da chave (`standards` ou `styles`) ou `null` quando não aplicável.
     */
    public static function metadataListKey(string $slug): ?string
    {
        return match ($slug) {
            BibliografiaAgent::SLUG => 'standards',
            TextoAgent::SLUG => 'styles',
            default => null,
        };
    }

    /**
     * Retorna a lista padrão de opções de metadados definida em código para o `slug`.
     *
     * @param string $slug Identificador do serviço.
     * @return list<string> Lista padrão de opções ou array vazio.
     */
    public static function defaultMetadataItemsForSlug(string $slug): array
    {
        return match ($slug) {
            BibliografiaAgent::SLUG => BibliografiaAgent::DEFAULT_STANDARDS,
            TextoAgent::SLUG => TextoAgent::DEFAULT_STYLES,
            default => [],
        };
    }

    /**
     * Retorna as variáveis dinâmicas suportadas na interpolação do prompt de cada serviço.
     *
     * @param string $slug Identificador do serviço.
     * @return array<string, string> Mapa de placeholder para sua descrição na interface.
     */
    public static function placeholdersForSlug(string $slug): array
    {
        return match ($slug) {
            BibliografiaAgent::SLUG => [
                '{$standard}' => 'Norma bibliográfica selecionada pelo usuário no formulário.',
                '{{ $standard }}' => 'Formato alternativo da norma bibliográfica.',
            ],
            TextoAgent::SLUG => [
                '{$style}' => 'Estilo/formato textual selecionado pelo usuário.',
                '{$availableStyles}' => 'Lista de estilos disponíveis configurados no sistema.',
            ],
            default => [],
        };
    }

    /**
     * Garante de forma idempotente que os prompts iniciais dos agentes existam na tabela `ai_prompts`.
     *
     * @return void
     */
    public static function ensureDefaultsExist(): void
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
            $hasActiveVersion = static::forSlug($slug)->active()->exists();

            static::firstOrCreate(
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

            self::forgetCache($slug);
        }
    }

    /**
     * Retorna as instruções de sistema associadas a um prompt pelo seu `slug`.
     *
     * @param string $slug Identificador do prompt.
     * @param array<string, mixed>|null $params Parâmetros opcionais para interpolação no template.
     * @param int|null $version Versão específica a recuperar (opcional; `null` usa a versão `active`).
     * @return string Instruções de sistema utilizadas pela IA.
     * @throws InvalidArgumentException Quando o prompt ou a versão solicitada não existir.
     */
    public static function systemPrompt(string $slug, ?array $params = [], ?int $version = null): string
    {
        $fallback = self::defaultTemplateForSlug($slug);

        return self::resolve($slug, $fallback, $params, $version);
    }

    /**
     * Retorna as instruções de sistema associadas a um prompt, sem parâmetros extras.
     *
     * @param string $slug Identificador do prompt.
     * @return string Instruções de sistema utilizadas pela IA.
     * @throws InvalidArgumentException Quando o prompt informado não existir.
     */
    public static function get(string $slug): string
    {
        return self::systemPrompt($slug);
    }

    /**
     * Renderiza um prompt para envio à API de IA, combinando as instruções
     * de sistema com o conteúdo fornecido pelo usuário.
     *
     * @param string $slug Identificador do prompt a ser utilizado.
     * @param string $userContent Conteúdo fornecido pelo usuário.
     * @return array<int, array{role: string, content: string}> Mensagens no formato Chat Completions.
     * @throws InvalidArgumentException Quando o prompt informado não existir.
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
     * Renderiza a estrutura de mensagens passando o texto do system prompt diretamente.
     *
     * @param string $systemPrompt Instruções de sistema já resolvidas.
     * @param string $userContent Conteúdo fornecido pelo usuário.
     * @return array<int, array{role: string, content: string}> Mensagens no formato Chat Completions.
     */
    public static function renderFromPrompt(string $systemPrompt, string $userContent): array
    {
        return [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userContent,
            ],
        ];
    }
}
