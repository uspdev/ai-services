<?php

namespace App\Http\Controllers;

use App\Models\AiPrompt;
use App\Services\AiService;
use Illuminate\Http\Request;

class BibliografiaController extends Controller
{

    public function __construct(private AiService $aiService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('bibliografia.index', ['referencias' => '']);
    }

    /**
     * Processa a lista de referências bibliográficas utilizando o serviço de IA.
     *
     * Este método aceita requisições convencionais (Web) e requisições via API (JSON),
     * retornando uma View renderizada ou um payload JSON estruturado com base no
     * cabeçalho `Accept` fornecido na requisição.
     *
     * @param  \Illuminate\Http\Request  $request  Instância da requisição contendo o campo 'referencias'.
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse Retorna View para requisições web ou JsonResponse para APIs.
     *
     * @throws \Illuminate\Validation\ValidationException Se a validação dos dados de entrada falhar.
     */
    public function processar(Request $request)
    {
        if ($request->isMethod('get')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Envie uma requisição POST com o campo "referencias" para processar.',
                ], 200);
            }

            return view('bibliografia.index');
        }

        $request->validate([
            'referencias' => ['required', 'string', 'max:10000'],
        ]);

        $referencias = $request->input('referencias');

        $message = AiPrompt::render('bibliografia', $referencias);
        $prompt = AiPrompt::systemPrompt('bibliografia');
        $resultadoRaw = $this->aiService->submit($message);
        $resultado = json_decode($resultadoRaw, true) ?? [];

        $data = [
            'referencias'   => $request->referencias,
            'respostas'     => $resultado['referencias'] ?? [],
            'explicacoes'   => $resultado['explicacoes'] ?? [],
            'confiancas'    => $resultado['confiancas'] ?? [],
            'estatisticas'  => $this->aiService->statistics(),
            'prompt'        => $prompt,
        ];

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $data,], 200);
        }

        return view('bibliografia.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
