<?php

namespace App\Models;

use App\Ai\Agents\BibliografiaAgent;
use App\Ai\Agents\EmentaAgent;
use App\Ai\Agents\TextoAgent;
use App\Enums\PromptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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
        $fallback = match ($slug) {
            BibliografiaAgent::SLUG => BibliografiaAgent::defaultTemplate(),
            EmentaAgent::SLUG => EmentaAgent::defaultTemplate(),
            TextoAgent::SLUG => TextoAgent::defaultTemplate(),
            default => null,
        };

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
