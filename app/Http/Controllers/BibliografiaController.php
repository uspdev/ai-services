<?php

namespace App\Http\Controllers;

use App\Ai\Agents\BibliografiaAgent;
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
 * Valida o formulário de bibliografia e apresenta os resultados web ou JSON.
 */
class BibliografiaController extends Controller
{
    use HandlesAiFailures;

    /**
     * Recebe o serviço compartilhado de execução dos agentes.
     *
     * @param AiService $aiService Serviço de IA com cache e estatísticas.
     * @return void
     */
    public function __construct(private AiService $aiService) {}

    /**
     * Exibe o formulário ou processa a lista de referências bibliográficas utilizando o serviço de IA.
     *
     * Este método aceita requisições convencionais (Web) e requisições via API (JSON),
     * retornando uma View renderizada ou um payload JSON estruturado com base no
     * cabeçalho `Accept` fornecido na requisição.
     *
     * @param Request $request Requisição com 'message' e 'standard' no POST.
     * @return View|JsonResponse|RedirectResponse Formulário, resultado ou erro de processamento.
     * @throws \Illuminate\Validation\ValidationException Se a validação dos dados de entrada falhar.
     */
    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $standards = BibliografiaAgent::standards();
        if ($request->isMethod('get')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Envie uma requisição POST com os campos "message" e "standard" para processar.',
                ], 200);
            }

            return view('bibliografia.index', [
                'standards' => $standards,
            ]);
        }

        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'standard' => ['required', 'string', Rule::in($standards)],
        ]);

        $referencias = $request->input('message');
        $standard = $request->input('standard');

        try {
            // O agente resolve o prompt ativo (ou fallback); o serviço devolve o array validado.
            $agent = new BibliografiaAgent($standard);
            $prompt = $agent->instructions();
            $resultado = $this->aiService->submit($agent, $referencias);
        } catch (AiException|RequestException|ConnectionException|RuntimeException|InvalidArgumentException|JsonException $exception) {
            return $this->aiFailureResponse($request, $exception);
        }

        $data = [
            'referencias' => $referencias,
            'respostas' => $resultado['referencias'] ?? [],
            'explicacoes' => $resultado['explicacoes'] ?? [],
            'confiancas' => $resultado['confiancas'] ?? [],
            'estatisticas' => $this->aiService->statistics(),
            'prompt' => $prompt,
            'standards' => $standards,
            'standard' => $standard,
        ];

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $data], 200);
        }

        return view('bibliografia.index', $data);
    }
}
