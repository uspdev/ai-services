<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DedalusService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Verifica a construção de termos, normalização de publicações, cache e resiliência do DedalusService.
 */
class DedalusServiceTest extends TestCase
{
    /**
     * Configura o ambiente de testes com endpoint Dedalus previsível e cache em memória.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'aiService.cacheRetention' => 604800,
            'dedalus.url' => 'https://app.abcd.usp.br/WSearch.php',
            'dedalus.library' => 'EESC',
            'dedalus.timeout' => 30,
        ]);
        URL::forceRootUrl('http://localhost');
        Http::preventStrayRequests();
        Log::spy();
    }

    /**
     * Confere que o termo de busca prioriza o título principal sem subtítulos e usa o autor como fallback.
     *
     * @return void
     */
    public function test_build_search_query_extracts_main_title_with_author_fallback(): void
    {
        $service = new DedalusService;

        $this->assertSame(
            'Introdução à mecânica dos sólidos',
            $service->buildSearchQuery([
                'autor' => 'POPOV, Egor P.',
                'titulo' => 'Introdução à mecânica dos sólidos: edição revisada',
            ])
        );

        // Quando o título está ausente ou vazio, utiliza o autor principal higienizado.
        $this->assertSame(
            'Paul Allen Tipler',
            $service->buildSearchQuery([
                'autor' => 'Paul Allen Tipler 1933-',
                'titulo' => '[s.n.]',
            ])
        );

        $this->assertSame(
            'Manual de hidráulica',
            $service->buildSearchQuery([
                'autor' => '[s.n.]',
                'titulo' => 'Manual de hidráulica',
            ])
        );

        $this->assertSame('', $service->buildSearchQuery(['autor' => 'N/A', 'titulo' => '-']));
    }

    /**
     * Confere a normalização das publicações, os parâmetros fixos e o reaproveitamento do cache.
     *
     * @return void
     */
    public function test_search_normalizes_publications_and_caches_successful_responses(): void
    {
        Http::fake([
            'https://app.abcd.usp.br/WSearch.php*' => Http::response([
                'numberOfResultsPerPage' => 20,
                'numberOfResultsTotal' => 15,
                'pageNumber' => 1,
                'publications' => [
                    [
                        'sysNo' => '000296236',
                        'base' => 'USP01',
                        'authors' => ['Paul Allen Tipler 1933-', 'Horácio Macedo'],
                        'coverUrl' => '',
                        'editor' => 'Rio de Janeiro Guanabara Dois',
                        'format' => '2 v.',
                        'idiom' => 'Português',
                        'ids01' => 'Livro',
                        'inHouse' => 1,
                        'locations' => [],
                        'mediaType' => 'Livro',
                        'publicationDate' => '1978',
                        'subjects' => ['Física'],
                        'title' => 'Fisica',
                    ],
                ],
            ]),
        ]);

        $service = new DedalusService;
        $first = $service->search('Introdução à mecânica dos sólidos');
        $second = $service->search('Introdução à mecânica dos sólidos');

        $this->assertSame($first, $second);
        $this->assertNull($first['error']);
        $this->assertSame('EESC', $first['library']);
        $this->assertSame(15, $first['total']);
        $this->assertCount(1, $first['publications']);
        $this->assertSame('000296236', $first['publications'][0]['sysNo']);
        $this->assertSame(['Paul Allen Tipler 1933-', 'Horácio Macedo'], $first['publications'][0]['authors']);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return str_starts_with($request->url(), 'https://app.abcd.usp.br/WSearch.php')
                && $request['search'] === 'Introdução à mecânica dos sólidos'
                && $request['library'] === 'EESC'
                && $request['resourceType'] === 'books'
                && $request['options'] === 'byTitle';
        });
    }

    /**
     * Confere que falhas HTTP na API do Dedalus não interrompem o fluxo e não são cacheadas.
     *
     * @return void
     */
    public function test_search_handles_upstream_failures_gracefully_without_caching_errors(): void
    {
        Http::fake([
            'https://app.abcd.usp.br/WSearch.php*' => Http::sequence()
                ->push(['error' => 'unavailable'], 503)
                ->push(['numberOfResultsTotal' => 1, 'publications' => [['sysNo' => '001', 'title' => 'Física']]], 200),
        ]);

        $service = new DedalusService;

        $failed = $service->search('Tipler');
        $this->assertSame(0, $failed['total']);
        $this->assertSame([], $failed['publications']);
        $this->assertNotNull($failed['error']);

        // Como erros não são cacheados, a tentativa seguinte consulta a API novamente.
        $recovered = $service->search('Tipler');
        $this->assertNull($recovered['error']);
        $this->assertSame(1, $recovered['total']);
        Http::assertSentCount(2);
    }

    /**
     * Confere o endpoint autenticado GET /bibliografia/dedalus para refinamento de busca na interface.
     *
     * @return void
     */
    public function test_dedalus_endpoint_validates_and_returns_search_results(): void
    {
        Http::fake([
            'https://app.abcd.usp.br/WSearch.php*' => Http::response([
                'numberOfResultsTotal' => 2,
                'publications' => [
                    ['sysNo' => '000611341', 'title' => 'Física', 'authors' => ['Paul Allen Tipler']],
                ],
            ]),
        ]);

        $this->getJson(route('bibliografia.dedalus', ['search' => 'Tipler']))->assertUnauthorized();

        $this->actingAs((new User)->forceFill(['id' => 1, 'name' => 'Teste', 'email' => 'teste@example.com']));

        $this->getJson(route('bibliografia.dedalus'))->assertUnprocessable()->assertJsonValidationErrors('search');

        $this->getJson(route('bibliografia.dedalus', ['search' => 'Tipler', 'library' => 'IFSC']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.library', 'IFSC')
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.publications.0.sysNo', '000611341');
    }
}
