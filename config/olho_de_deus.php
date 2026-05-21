<?php

return [

    /*
    | Intervalo sugerido ao cliente (ms). O navegador só consulta com a aba visível.
    */
    'poll_interval_ms' => (int) env('OLHO_DE_DEUS_POLL_MS', 45_000),

    /*
    | Limite de requisições de poll por usuário por minuto (throttle Laravel).
    */
    'poll_max_per_minute' => (int) env('OLHO_DE_DEUS_POLL_MAX_PER_MINUTE', 4),

    'frete_kg_maximo' => (float) env('OLHO_DE_DEUS_FRETE_KG_MAX', 0.50),

    'lookback_horas_inicial' => (int) env('OLHO_DE_DEUS_LOOKBACK_HORAS', 24),

    'max_movimentacoes_por_poll' => (int) env('OLHO_DE_DEUS_MAX_MOV_POR_POLL', 25),

    'max_movimentacoes_carga_inicial' => (int) env('OLHO_DE_DEUS_MAX_MOV_CARGA_INICIAL', 100),

    'max_alertas_por_poll' => (int) env('OLHO_DE_DEUS_MAX_ALERTAS_POR_POLL', 50),

    'perda_descarte_min_reais' => (float) env('OLHO_DE_DEUS_PERDA_DESCARTE_MIN', 500),

];
