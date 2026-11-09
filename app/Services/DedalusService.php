<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta o acervo bibliográfico Dedalus/ABCD da USP a partir de termos extraídos das referências.
 *
 * Constrói termos de busca enxutos a partir do par `{autor, titulo}` retornado pelo
 * `BibliografiaAgent`, executa a requisição HTTP ao endpoint `WSearch.php` com cache
 * opcional e normaliza os registros retornados para exibição na interface e na API JSON.
 */
class DedalusService
{
    /**
     * Lista de marcadores vazios ou desconhecidos que não devem compor o termo de busca.
     *
     * @var list<string>
     */
    private const PLACEHOLDERS = [
        '',
        '-',
        'n/a',
        'na',
        's.n.',
        '[s.n.]',
        's.l.',
        '[s.l.]',
        's.d.',
        '[s.d.]',
        'autor desconhecido',
        'sem autor',
    ];

    /**
     * Tipo de recurso padrão consultado no Dedalus.
     *
     * @var string
     */
    private const DEFAULT_RESOURCE_TYPE = 'books';

    /**
     * Critério de busca padrão consultado no Dedalus.
     *
     * @var string
     */
    private const DEFAULT_OPTIONS = 'byTitle';

    /**
     * Monta o termo de busca priorizando o título principal da obra (compatível com `options=byTitle`),
     * recorrendo ao autor principal apenas quando o título estiver ausente.
     *
     * @param array<string, mixed> $item Par com chaves `autor` e `titulo` extraído pelo agente.
     * @return string Termo higienizado para o parâmetro `search` do Dedalus.
     */
    public function buildSearchQuery(array $item): string
    {
        $titulo = $this->extractPrimaryTitle((string) ($item['titulo'] ?? ''));
        if ($titulo !== '') {
            return (string) preg_replace('/\s+/u', ' ', $titulo);
        }

        $autor = $this->extractPrimaryAuthor((string) ($item['autor'] ?? ''));

        return (string) preg_replace('/\s+/u', ' ', $autor);
    }

    /**
     * Prepara os metadados e termos de busca para cada referência sem realizar chamadas HTTP.
     *
     * Usado na resposta principal do `POST /bibliografia` para que a consulta ao Dedalus
     * ocorra de forma assíncrona via AJAX no navegador sem aumentar o tempo de resposta da IA.
     *
     * @param array<int, mixed> $buscaDedalus Lista de pares `{autor, titulo}` gerada pelo `BibliografiaAgent`.
     * @param string|null $library Sigla da biblioteca no Dedalus (usa `dedalus.library` se omitida).
     * @return list<array{
     *     autor: string,
     *     titulo: string,
     *     query: string,
     *     library: string
     * }> Metadados de busca indexados na mesma ordem das referências.
     */
    public function prepareBatch(array $buscaDedalus, ?string $library = null): array
    {
        $resolvedLibrary = trim((string) ($library ?? config('dedalus.library')));
        $items = [];

        foreach (array_values($buscaDedalus) as $item) {
            $normalizedItem = is_array($item) ? $item : [];
            $items[] = [
                'autor' => trim((string) ($normalizedItem['autor'] ?? '')),
                'titulo' => trim((string) ($normalizedItem['titulo'] ?? '')),
                'query' => $this->buildSearchQuery($normalizedItem),
                'library' => $resolvedLibrary,
            ];
        }

        return $items;
    }

    /**
     * Executa a busca de referências em lote mantendo a correspondência posicional com a lista de entrada.
     *
     * @param array<int, mixed> $buscaDedalus Lista de pares `{autor, titulo}` gerada pelo `BibliografiaAgent`.
     * @param string|null $library Sigla da biblioteca no Dedalus (usa `dedalus.library` se omitida).
     * @return list<array{
     *     autor: string,
     *     titulo: string,
     *     query: string,
     *     library: string,
     *     url: string,
     *     total: int,
     *     publications: list<array<string, mixed>>,
     *     error: string|null
     * }> Resultados do Dedalus indexados na mesma ordem das referências.
     */
    public function searchBatch(array $buscaDedalus, ?string $library = null): array
    {
        $results = [];

        foreach ($this->prepareBatch($buscaDedalus, $library) as $prepared) {
            $searchResult = $this->search($prepared['query'], $prepared['library']);

            $results[] = [
                'autor' => $prepared['autor'],
                'titulo' => $prepared['titulo'],
                ...$searchResult,
            ];
        }

        return $results;
    }

    /**
     * Consulta o endpoint WSearch do Dedalus para um termo específico e normaliza as publicações.
     *
     * Falhas de rede ou respostas inválidas do serviço externo são registradas em log e
     * convertidas em um retorno vazio com mensagem de erro amigável, evitando interromper
     * o processamento principal das referências bibliográficas pela IA.
     *
     * @param string $query Termo de busca a consultar no parâmetro `search`.
     * @param string|null $library Sigla da biblioteca no Dedalus (padrão configurado em `dedalus.library`).
     * @return array{
     *     query: string,
     *     library: string,
     *     url: string,
     *     total: int,
     *     publications: list<array<string, mixed>>,
     *     error: string|null
     * } Resultado normalizado da busca no Dedalus.
     */
    public function search(string $query, ?string $library = null): array
    {
        $cleanQuery = trim((string) preg_replace('/\s+/u', ' ', $query));
        $endpoint = trim((string) config('dedalus.url'));
        $resolvedLibrary = trim((string) ($library ?? config('dedalus.library')));

        if ($cleanQuery === '' || $endpoint === '') {
            return $this->emptyResult($cleanQuery, $resolvedLibrary, '');
        }

        $queryParams = ['search' => $cleanQuery];
        if ($resolvedLibrary !== '') {
            $queryParams['library'] = $resolvedLibrary;
        }
        $queryParams['resourceType'] = self::DEFAULT_RESOURCE_TYPE;
        $queryParams['options'] = self::DEFAULT_OPTIONS;

        $separator = str_contains($endpoint, '?') ? '&' : '?';
        $fullUrl = $endpoint.$separator.http_build_query($queryParams);
        $cacheRetention = (int) config('aiService.cacheRetention', 0);
        $cacheKey = 'dedalus_search_'.md5(json_encode([$endpoint, $queryParams]));

        if ($cacheRetention > 0 && Cache::has($cacheKey)) {
            /** @var array{query: string, library: string, url: string, total: int, publications: list<array<string, mixed>>, error: string|null} $cached */
            $cached = Cache::get($cacheKey);

            return $cached;
        }

        $timeout = max(1, (int) config('dedalus.timeout', 30));

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->get($endpoint, $queryParams);

            if (! $response->successful()) {
                Log::warning('Dedalus search returned non-success status.', [
                    'status' => $response->status(),
                    'query' => $cleanQuery,
                    'library' => $resolvedLibrary,
                ]);

                return $this->emptyResult(
                    $cleanQuery,
                    $resolvedLibrary,
                    $fullUrl,
                    'Não foi possível consultar o acervo Dedalus no momento.'
                );
            }

            $payload = $response->json();
            if (! is_array($payload) || ! isset($payload['publications']) || ! is_array($payload['publications'])) {
                Log::warning('Dedalus search returned unexpected payload structure.', [
                    'query' => $cleanQuery,
                    'library' => $resolvedLibrary,
                ]);

                return $this->emptyResult(
                    $cleanQuery,
                    $resolvedLibrary,
                    $fullUrl,
                    'Resposta inválida recebida ao consultar o acervo Dedalus.'
                );
            }

            $publications = [];
            foreach ($payload['publications'] as $pub) {
                if (is_array($pub)) {
                    $publications[] = $this->normalizePublication($pub);
                }
            }

            $result = [
                'query' => $cleanQuery,
                'library' => $resolvedLibrary,
                'url' => $fullUrl,
                'total' => isset($payload['numberOfResultsTotal']) && is_numeric($payload['numberOfResultsTotal'])
                    ? (int) $payload['numberOfResultsTotal']
                    : count($publications),
                'publications' => $publications,
                'error' => null,
            ];

            if ($cacheRetention > 0) {
                Cache::put($cacheKey, $result, $cacheRetention);
            }

            return $result;
        } catch (Throwable $exception) {
            Log::warning('Dedalus search failed with exception.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'query' => $cleanQuery,
                'library' => $resolvedLibrary,
            ]);

            return $this->emptyResult(
                $cleanQuery,
                $resolvedLibrary,
                $fullUrl,
                'Não foi possível consultar o acervo Dedalus no momento.'
            );
        }
    }

    /**
     * Extrai o sobrenome ou segmento principal do autor descartando iniciais, datas e marcadores vazios.
     *
     * @param string $rawAuthor Autor bruto informado em `busca_dedalus`.
     * @return string Sobrenome ou nome principal do autor para busca.
     */
    private function extractPrimaryAuthor(string $rawAuthor): string
    {
        $cleaned = trim($rawAuthor);
        if ($this->isPlaceholder($cleaned)) {
            return '';
        }

        // Remove expressões como "et al." e datas de nascimento/morte (ex.: "1933-" ou "1933-2010").
        $cleaned = (string) preg_replace('/\bet\s+al\.?/iu', '', $cleaned);
        $cleaned = (string) preg_replace('/(?<!\d)\d{4}-\d{0,4}(?!\d)/u', '', $cleaned);

        // Quando estiver no formato "SOBRENOME, Nome", utiliza a parte anterior à primeira vírgula ou ponto e vírgula.
        $segments = preg_split('/[,;]/u', $cleaned);
        $primary = trim((string) ($segments[0] ?? $cleaned), " \t\n\r\0\x0B.,;:");

        return $this->isPlaceholder($primary) ? '' : $primary;
    }

    /**
     * Extrai o título principal da obra descartando subtítulos longos e marcadores vazios.
     *
     * @param string $rawTitle Título bruto informado em `busca_dedalus`.
     * @return string Título principal higienizado.
     */
    private function extractPrimaryTitle(string $rawTitle): string
    {
        $cleaned = trim($rawTitle);
        if ($this->isPlaceholder($cleaned)) {
            return '';
        }

        // Separa o título principal antes de ":" ou ";" caso produza um segmento não vazio.
        $segments = preg_split('/[:;]/u', $cleaned);
        $primary = trim((string) ($segments[0] ?? $cleaned), " \t\n\r\0\x0B.,;:");

        if ($primary === '') {
            $primary = trim($cleaned, " \t\n\r\0\x0B.,;:");
        }

        return $this->isPlaceholder($primary) ? '' : $primary;
    }

    /**
     * Verifica se o valor corresponde a um marcador de ausência de informação.
     *
     * @param string $value Texto a verificar.
     * @return bool Verdadeiro quando o texto não representa um termo pesquisável.
     */
    private function isPlaceholder(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), self::PLACEHOLDERS, true);
    }

    /**
     * Normaliza os campos de uma publicação retornada pela API do Dedalus.
     *
     * @param array<string, mixed> $pub Objeto bruto de publicação do WSearch.
     * @return array{
     *     sysNo: string,
     *     base: string,
     *     title: string,
     *     authors: list<string>,
     *     editor: string,
     *     publicationDate: string,
     *     format: string,
     *     idiom: string,
     *     mediaType: string,
     *     subjects: list<string>,
     *     inHouse: int,
     *     locations: list<mixed>,
     *     coverUrl: string
     * } Registro de publicação padronizado.
     */
    private function normalizePublication(array $pub): array
    {
        $authors = [];
        if (is_array($pub['authors'] ?? null)) {
            foreach ($pub['authors'] as $author) {
                if (is_scalar($author) && trim((string) $author) !== '') {
                    $authors[] = trim((string) $author);
                }
            }
        }

        $subjects = [];
        if (is_array($pub['subjects'] ?? null)) {
            foreach ($pub['subjects'] as $subject) {
                if (is_scalar($subject) && trim((string) $subject) !== '') {
                    $subjects[] = trim((string) $subject);
                }
            }
        }

        return [
            'sysNo' => trim((string) ($pub['sysNo'] ?? '')),
            'base' => trim((string) ($pub['base'] ?? '')),
            'title' => trim((string) ($pub['title'] ?? '')),
            'authors' => $authors,
            'editor' => trim((string) ($pub['editor'] ?? '')),
            'publicationDate' => trim((string) ($pub['publicationDate'] ?? '')),
            'format' => trim((string) ($pub['format'] ?? '')),
            'idiom' => trim((string) ($pub['idiom'] ?? '')),
            'mediaType' => trim((string) ($pub['mediaType'] ?? ($pub['ids01'] ?? ''))),
            'subjects' => $subjects,
            'inHouse' => (int) ($pub['inHouse'] ?? 0),
            'locations' => is_array($pub['locations'] ?? null) ? array_values($pub['locations']) : [],
            'coverUrl' => trim((string) ($pub['coverUrl'] ?? '')),
        ];
    }

    /**
     * Constrói a estrutura padrão de retorno vazio ou com erro.
     *
     * @param string $query Termo pesquisado.
     * @param string $library Biblioteca alvo.
     * @param string $url URL completa da consulta.
     * @param string|null $error Mensagem de erro amigável, se houver.
     * @return array{
     *     query: string,
     *     library: string,
     *     url: string,
     *     total: int,
     *     publications: list<array<string, mixed>>,
     *     error: string|null
     * } Estrutura vazia padronizada.
     */
    private function emptyResult(string $query, string $library, string $url, ?string $error = null): array
    {
        return [
            'query' => $query,
            'library' => $library,
            'url' => $url,
            'total' => 0,
            'publications' => [],
            'error' => $error,
        ];
    }
}