<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Verifica o acesso autenticado e a renderização HTML do glossário.
 */
class GlossarioTest extends TestCase
{
    /**
     * Configura um usuário autenticado e evita dependências de menus no banco de dados.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'laravel-usp-theme.menu' => [],
            'laravel-usp-theme.right_menu' => [],
            'laravel-usp-theme.mensagensFlash' => true,
        ]);
        View::share('menu', []);
        View::share('right_menu', []);
        URL::forceRootUrl('http://localhost');
        $this->actingAs((new User)->forceFill([
            'id' => 1,
            'name' => 'Teste',
            'email' => 'teste@example.com',
        ]));
    }

    /**
     * Confere que a rota exibe o conteúdo Markdown convertido em elementos HTML.
     *
     * @return void
     */
    public function test_glossario_is_rendered_as_html(): void
    {
        $this->get(route('glossario.index'))
            ->assertOk()
            ->assertSee('<h1>', false)
            ->assertSee('GLOSSÁRIO DE INTELIGÊNCIA ARTIFICIAL', false)
            ->assertSee('<strong>Guia Prático</strong>', false);
    }
}
