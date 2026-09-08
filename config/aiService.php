<?php

return [
    # Provedor de IA padrão
    'defaultProvider' => env('AI_PROVIDER', 'litellm'),

    'providers' => [
        'litellm' => [
            'url' => env('LITELLM_URL'),
            'key' => env('LITELLM_API_KEY'),
            'model' => env('LITELLM_MODEL'),
        ],
    ],

    # tempo de retenção do cache local em segundos (padrão: 604800 = 7 dias)
    'cacheRetention' => (int) env('AI_CACHE_RETENTION', '604800'),

];
