<?php

return [
    // Opção própria da aplicação: cache das respostas validadas pelo AiService.
    // Provider, URL, chave e modelo são configurados somente em config/ai.php.
    // Retenção em segundos (padrão: 604800 = 7 dias); zero desabilita o cache.
    'cacheRetention' => (int) env('AI_CACHE_RETENTION', '604800'),

    // Período de exibição (em meses) dos logs de auditoria de prompts na interface (padrão: 12 meses).
    'promptLogRetentionMonths' => (int) env('AI_PROMPT_LOG_RETENTION_MONTHS', '12'),
];
