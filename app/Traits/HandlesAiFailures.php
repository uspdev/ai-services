<?php

namespace App\Traits;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use RuntimeException;
use Throwable;

/**
 * Compartilha o tratamento padronizado de falhas de execução de IA entre controllers.
 *
 * Converte exceções do SDK e do cliente HTTP em respostas web (redirect com alerta)
 * ou JSON estruturado sem expor detalhes internos da conexão com o provider.
 */
trait HandlesAiFailures
{
    /**
     * Converte uma falha da execução de IA em uma mensagem web ou JSON apropriada.
     *
     * @param Request $request Requisição original usada para escolher o formato e preservar a entrada.
     * @param Throwable $exception Falha do provider, configuração ou validação da resposta de IA.
     * @return JsonResponse|RedirectResponse Erro JSON ou retorno ao formulário com mensagem e entrada.
     */
    protected function aiFailureResponse(Request $request, Throwable $exception): JsonResponse|RedirectResponse
    {
        // As mensagens do provider podem conter detalhes da conexão. A resposta
        // pública e o registro usam mensagens próprias e somente a classe do erro.
        Log::warning('Falha no processamento de IA.', [
            'exception_type' => $exception::class,
            'route' => $request->route()?->getName(),
        ]);

        [$status, $message] = match (true) {
            $this->isTimeout($exception) => [504, 'O serviço de IA não respondeu a tempo. Tente novamente.'],
            $exception instanceof RateLimitedException => [429, 'O serviço de IA atingiu o limite de solicitações. Aguarde e tente novamente.'],
            $exception instanceof ProviderConnectionException,
            $exception instanceof ConnectionException => [503, 'Não foi possível conectar ao serviço de IA. Tente novamente mais tarde.'],
            $exception instanceof ProviderOverloadedException,
            $exception instanceof InsufficientCreditsException,
            $exception instanceof InvalidArgumentException,
            $exception instanceof JsonException => [503, 'O serviço de IA está indisponível no momento. Tente novamente mais tarde.'],
            $exception instanceof RuntimeException => [502, 'A IA retornou uma resposta inválida ou incompleta. Tente novamente.'],
            default => [502, 'Não foi possível processar a solicitação no serviço de IA. Tente novamente.'],
        };

        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        // Os alertas do tema exibem o erro; somente os campos dos formulários
        // são preservados na sessão, sem reaproveitar tokens ou outros parâmetros.
        return redirect()->route($request->route()?->getName())
            ->withInput($request->only(['message', 'standard', 'mensagem', 'rascunho', 'style', 'contexto']))
            ->withErrors(['ai' => $message]);
    }

    /**
     * Identifica timeout nas causas originais mantidas pelo SDK e pelo cliente HTTP.
     *
     * @param Throwable $exception Falha que pode envolver um timeout de conexão ou resposta.
     * @return bool True quando houver timeout cURL ou status HTTP 408/504 na cadeia de causas.
     */
    protected function isTimeout(Throwable $exception): bool
    {
        // O SDK envolve os erros HTTP; percorra as causas sem analisar ou expor seu texto.
        do {
            if ($exception instanceof ConnectException && ($exception->getHandlerContext()['errno'] ?? null) === 28) {
                return true;
            }
            if ($exception instanceof RequestException && in_array($exception->response->status(), [408, 504], true)) {
                return true;
            }
        } while ($exception = $exception->getPrevious());

        return false;
    }
}

