<?php

namespace App\Http\Controllers;

use App\Ai\Agents\BibliografiaAgent;
use App\Services\AiService;
use App\Services\DedalusService;
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
 * Valida o formulário de bibliografia, consulta o Dedalus e apresenta os resultados web ou JSON.
 */
class BibliografiaController extends Controller
{
    use HandlesAiFailures;

    /**
     * Recebe os serviços de execução dos agentes de IA e de consulta ao acervo Dedalus.
     *
     * @param AiService $aiService Serviço de IA com cache e estatísticas.
     * @param DedalusService $dedalusService Serviço de busca no catálogo Dedalus/ABCD.
     * @return void
     */
    public function __construct(
        private AiService $aiService,
        private DedalusService $dedalusService,
    ) {}

    /**
     * Exibe o formulário ou processa a lista de referências bibliográficas utilizando o serviço de IA.
     *
     * Este método aceita requisições convencionais (Web) e requisições via API (JSON),
     * retornando uma View renderizada ou um payload JSON estruturado com base no
     * cabeçalho `Accept` fornecido na requisição, acompanhado dos resultados do Dedalus.
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

        // Prepara apenas os termos de busca para que a interface consulte o Dedalus via AJAX.
        $buscaDedalus = $resultado['busca_dedalus'] ?? [];
        $dedalus = $this->dedalusService->prepareBatch($buscaDedalus);

        $data = [
            'referencias' => $referencias,
            'respostas' => $resultado['referencias'] ?? [],
            'explicacoes' => $resultado['explicacoes'] ?? [],
            'confiancas' => $resultado['confiancas'] ?? [],
            'buscaDedalus' => $buscaDedalus,
            'dedalus' => $dedalus,
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

    /**
     * Consulta o acervo Dedalus sob demanda para permitir refinar o termo de busca na interface.
     *
     * @param Request $request Requisição GET com o parâmetro `search` e opcionalmente `library`.
     * @return JsonResponse Resultado normalizado da busca no Dedalus.
     * @throws \Illuminate\Validation\ValidationException Se o parâmetro `search` não for informado.
     */
    public function dedalus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'max:500'],
            'library' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->dedalusService->search(
            $validated['search'],
            $validated['library'] ?? null,
        );

        return response()->json([
            'success' => $result['error'] === null,
            'data' => $result,
        ], 200);
    }
}
