<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use UspTheme;

/**
 * Exibe o glossário de inteligência artificial convertido de Markdown para HTML.
 */
class GlossarioController extends Controller
{
    /**
     * Carrega o arquivo do glossário, marca o menu correspondente como ativo e exibe a página.
     *
     * @return View Página do glossário renderizado.
     */
    public function index(): View
    {
        UspTheme::activeUrl('glossario');

        return view('glossario.index', [
            'glossarioHtml' => md2html(File::get(base_path('docs/glossario.md'))),
        ]);
    }
}
