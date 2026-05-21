<?php

return [
    'poll_interval_ms' => (int) env('DASHBOARD_FINANCEIRO_POLL_MS', 45_000),

    'poll_max_per_minute' => (int) env('DASHBOARD_FINANCEIRO_POLL_MAX_PER_MINUTE', 4),
];
