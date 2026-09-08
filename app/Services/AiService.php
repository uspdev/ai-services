<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiService
{
    private string $url;
    private string $key;
    private string $model;
    private int $cacheRetention;

    private ?array $statistics = null;


    public function __construct()
    {
        $config = config('aiService');
        $provider = $config['defaultProvider'];
        $providerConfig = $config['providers'][$provider]
            ?? throw new RuntimeException(
                "Provedor de IA não configurado: {$provider}"
            );

        $this->url = rtrim($providerConfig['url'], '/');
        $this->key = $providerConfig['key'];
        $this->model = $providerConfig['model'];
        $this->cacheRetention = $config['cacheRetention'];
    }

    /**
     * Envia uma mensagem para o modelo de IA e retorna o conteúdo da resposta.
     *
     * @param array $message Mensagens no formato esperado pela API Chat Completions.
     * @return string Conteúdo textual retornado pelo modelo.
     *
     * @throws \RuntimeException Quando a resposta da IA não for concluída normalmente.
     * @throws \Illuminate\Http\Client\RequestException Em caso de erro na requisição HTTP.
     */
    public function submit(array $message): string
    {
        $startTime = microtime(true);
        $cacheKey = 'ai:submit:' . hash('sha256', $this->model . '|' . json_encode($message));
        $cachedAt = null;
        $wasCached = true;

        $res = Cache::remember(
            $cacheKey,
            now()->addSeconds($this->cacheRetention),
            function () use ($message, &$wasCached, &$cachedAt) {
                $wasCached = false;
                $cachedAt = now()->toIso8601String();
                $response = Http::withToken($this->key)
                    ->timeout(60)
                    ->post(
                        $this->url . '/v1/chat/completions',
                        ['model' => $this->model, 'messages' => $message]
                    );
                $response->throw();
                return $response->json();
            }
        );

        $this->statistics = [
            'usage' => $res['usage'] ?? null,
            'model' => $res['model'] ?? $this->model,
            'id' => $res['id'] ?? null,
            'finish_reason' => $choice['finish_reason'] ?? null,
            'duration' => round(microtime(true) - $startTime, 2), //(em segundos)
            'cached' => $wasCached,
            'cached_at' => $cachedAt,
        ];
        $choice = $res['choices'][0] ?? null;

        if (($choice['finish_reason'] ?? null) !== 'stop') {
            throw new RuntimeException(
                'Resposta da IA não foi concluída normalmente.'
            );
        }

        return $choice['message']['content'] ?? '';
    }

    /**
     * Retorna as estatísticas da última requisição à IA.
     *
     * @return array|null Estatísticas de uso retornadas pela API ou null quando indisponíveis.
     */
    public function statistics(): ?array
    {
        return $this->statistics;
    }
}
