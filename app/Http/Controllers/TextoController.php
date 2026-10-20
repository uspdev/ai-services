<?php

namespace App\Http\Controllers;

use App\Ai\Agents\TextoAgent;
use App\Services\AiService;
use App\Traits\HandlesAiFailures;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Exceptions\AiException;
use RuntimeException;

/**
 * Valida o formulário de elaboração de texto e apresenta os resultados web ou JSON.
 */
class TextoController extends Controller
{
    use HandlesAiFailures;

    /**
     * Recebe o serviço compartilhado de execução dos agentes de IA.
     *
     * @param AiService $aiService Serviço de IA com cache e estatísticas.
     * @return void
     */
    public function __construct(private AiService $aiService) {}

    /**
     * Exibe o formulário ou elabora o texto a partir do rascunho utilizando o serviço de IA.
     *
     * Aceita requisições convencionais (Web) e requisições via API (JSON), retornando
     * a View renderizada ou o payload JSON estruturado conforme o cabeçalho `Accept`.
     *
     * @param Request $request Requisição com 'rascunho', 'style' e opcionalmente 'contexto' no POST.
     * @return View|JsonResponse|RedirectResponse Formulário, resultado elaborado ou erro de processamento.
     * @throws \Illuminate\Validation\ValidationException Se a validação dos dados de entrada falhar.
     */
    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $styles = TextoAgent::styles();

        if ($request->isMethod('get')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Envie uma requisição POST com os campos "rascunho" e "style" para processar.',
                ], 200);
            }

            return view('texto.index', [
                'styles' => $styles,
            ]);
        }

        $request->validate([
            'rascunho' => ['required', 'string', 'max:10000'],
            'style' => ['required', 'string', Rule::in($styles)],
            'contexto' => ['nullable', 'string', 'max:1000'],
        ]);

        $rascunho = (string) $request->input('rascunho');
        $style = (string) $request->input('style');
        $contexto = trim((string) $request->input('contexto', ''));

        // Quando o usuário fornece um contexto/destinatário adicional, anexa-o antes do rascunho.
        $entrada = $contexto !== ''
            ? "Contexto/Objetivo: {$contexto}\n\nRascunho:\n{$rascunho}"
            : $rascunho;

        try {
            $agent = new TextoAgent($style);
            $prompt = $agent->instructions();
            $resultado = $this->aiService->submit($agent, $entrada);
        } catch (AiException|RequestException|ConnectionException|RuntimeException|InvalidArgumentException|JsonException $exception) {
            return $this->aiFailureResponse($request, $exception);
        }

        $data = [
            'rascunho' => $rascunho,
            'contexto' => $contexto,
            'style' => $style,
            'styles' => $styles,
            'textoElaborado' => $resultado['texto_elaborado'] ?? '',
            'estiloAplicado' => $resultado['estilo_aplicado'] ?? $style,
            'assuntoSugerido' => $resultado['assunto_sugerido'] ?? '',
            'melhorias' => $resultado['melhorias'] ?? [],
            'observacoes' => $resultado['observacoes'] ?? '',
            'estatisticas' => $this->aiService->statistics(),
            'prompt' => $prompt,
        ];

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $data], 200);
        }

        return view('texto.index', $data);
    }
}

