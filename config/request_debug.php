<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Log temporário de requisições (diagnóstico de lentidão)
    |--------------------------------------------------------------------------
    |
    | Ative com REQUEST_DEBUG_ENABLED=true no .env (recomendado só em local).
    | Cada requisição web gera uma linha JSON em storage/logs/request-debug.jsonl.
    | Remova o middleware e este arquivo quando não precisar mais.
    |
    */

    'enabled' => (bool) env('REQUEST_DEBUG_ENABLED', false),

    /*
    | Rastreamento E2E no navegador (clique → rede → load).
    */
    'client_tracking' => (bool) env('REQUEST_DEBUG_CLIENT_TRACKING', true),

    'path' => storage_path('logs/request-debug.jsonl'),

    /*
    | Requisições com duração >= este valor recebem slow: true no JSON.
    */
    'slow_threshold_ms' => (int) env('REQUEST_DEBUG_SLOW_MS', 2000),

    'log_queries' => (bool) env('REQUEST_DEBUG_LOG_QUERIES', true),

    'max_queries_stored' => (int) env('REQUEST_DEBUG_MAX_QUERIES', 50),

    'slow_query_ms' => (int) env('REQUEST_DEBUG_SLOW_QUERY_MS', 100),

    'high_query_count' => (int) env('REQUEST_DEBUG_HIGH_QUERY_COUNT', 30),

    'repeated_query_min' => (int) env('REQUEST_DEBUG_REPEATED_QUERY_MIN', 5),

    'high_memory_mb' => (int) env('REQUEST_DEBUG_HIGH_MEMORY_MB', 128),

    'sql_max_length' => 400,

    'ignore_paths' => [
        'up',
        'favicon.ico',
        'assets/*',
        'build/*',
    ],

    'redact_input_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
    ],

];
