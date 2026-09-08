<?php

namespace App\Http\Controllers;

use App\Models\AiPrompt;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BibliografiaController extends Controller
{

    public function __construct(private AiService $aiService) {}

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
    public function bibliografia(Request $request)
    {
        $standards = AiPrompt::getBibliografiaStandards();
        if ($request->isMethod('get')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Envie uma requisição POST com o campo "referencias" para processar.',
                ], 200);
            }

            return view(
                'bibliografia.index',
                [
                    'standards' => $standards,
                ]
            );
        }

        $request->validate([
            'message' => ['required', 'string', 'max:10000'],
            'standard' => ['required','string',Rule::in($standards)
            ],
        ]);

        $referencias = $request->input('message');
        $standard = $request->input('standard');

        $prompt = AiPrompt::systemPrompt('bibliografia', ['standard' => $standard]);
        $message = AiPrompt::renderFromPrompt($prompt, $referencias);
        $resultadoRaw = $this->aiService->submit($message);
        $resultado = json_decode($resultadoRaw, true) ?? [];

        $data = [
            'referencias'   => $referencias,
            'respostas'     => $resultado['referencias'] ?? [],
            'explicacoes'   => $resultado['explicacoes'] ?? [],
            'confiancas'    => $resultado['confiancas'] ?? [],
            'estatisticas'  => $this->aiService->statistics(),
            'prompt'        => $prompt,
            'standards'      => $standards,
            'standard'      => $standard,
        ];

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $data,], 200);
        }

        return view('bibliografia.index', $data);
    }


    public function ementa(Request $request)
    {
        if ($request->isMethod('get')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'mensagem' => 'Envie uma requisição POST com o campo "referencias" para processar.',
                ], 200);
            }

            $standards = AiPrompt::getBibliografiaStandards();

            return view('ementa.index', compact('standards'));
        }

        $request->validate([
            'mensagem' => ['required', 'string', 'max:10000'],
        ]);

        $mensagem = $request->input('mensagem');

        $message = AiPrompt::render('ementa', $mensagem);
        $prompt = AiPrompt::systemPrompt('ementa');
        $resultadoRaw = $this->aiService->submit($message);
        $resultado = json_decode($resultadoRaw, true) ?? [];


        // dd($resultado);

        $data = [
            'mensagem'   => $mensagem,
            'resposta'     => $resultado['texto_revisado'] ?? [],
            'explicacoes'   => $resultado['explicacoes'] ?? [],
            'analiseIngles'   => $resultado['analise_ingles'] ?? [],
            'score'    => $resultado['score'] ?? [],
            'estatisticas'  => $this->aiService->statistics(),
            'prompt'        => $prompt,
        ];

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $data,], 200);
        }

        return view('ementa.index', $data);
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
