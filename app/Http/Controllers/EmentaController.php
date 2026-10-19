<?php

namespace App\Http\Controllers;

use App\Ai\Agents\EmentaAgent;
use App\Services\AiService;
use App\Traits\HandlesAiFailures;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Exceptions\AiException;
use RuntimeException;

/**
 * Valida o formulário de revisão de ementas e apresenta os resultados web ou JSON.
 */
class EmentaController extends Controller
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
     * Exibe o formulário ou processa a revisão da ementa utilizando o serviço de IA.
     *
     * @param Request $request Requisição com o campo 'mensagem' no POST.
     * @return View|JsonResponse|RedirectResponse Formulário, revisão ou erro de processamento.
     * @throws \Illuminate\Validation\ValidationException Quando a entrada não for válida.
     */
    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        if ($request->isMethod('get')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'mensagem' => 'Envie uma requisição POST com o campo "mensagem" para processar.',
                ], 200);
            }

            return view('ementa.index');
        }

        $request->validate([
            'mensagem' => ['required', 'string', 'max:10000'],
        ]);

        $mensagem = $request->input('mensagem');

        try {
            // A entrada permanece separada das instruções resolvidas pelo agente.
            $agent = new EmentaAgent;
            $prompt = $agent->instructions();
            $resultado = $this->aiService->submit($agent, $mensagem);
        } catch (AiException|RequestException|ConnectionException|RuntimeException|InvalidArgumentException|JsonException $exception) {
            return $this->aiFailureResponse($request, $exception);
        }

        $data = [
            'mensagem' => $mensagem,
            'resposta' => $resultado['texto_revisado'] ?? [],
            'explicacoes' => $resultado['explicacoes'] ?? [],
            // Mantém a chave pública existente, usando o campo efetivo do schema.
            'analiseIngles' => $resultado['explicacoes']['en'],
            'score' => $resultado['score'] ?? [],
            'estatisticas' => $this->aiService->statistics(),
            'prompt' => $prompt,
        ];

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $data], 200);
        }

        return view('ementa.index', $data);
    }
}

