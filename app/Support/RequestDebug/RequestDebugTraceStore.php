<?php

namespace App\Support\RequestDebug;

use App\Support\RequestDebugLogger;

class RequestDebugTraceStore
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findServerRecord(string $traceId): ?array
    {
        $path = config('request_debug.path');

        if (! is_readable($path)) {
            return null;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return null;
        }

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($lines[$i], true);

            if (! is_array($decoded)) {
                continue;
            }

            if (($decoded['trace_id'] ?? null) === $traceId
                && ($decoded['record_type'] ?? 'server') === 'server') {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<string, mixed>|null  $server
     * @return array<string, mixed>
     */
    public static function buildMergedRecord(array $client, ?array $server): array
    {
        /** @var array<string, mixed> $durations */
        $durations = is_array($client['durations_ms'] ?? null) ? $client['durations_ms'] : [];

        $serverMs = (float) ($server['duration_ms'] ?? 0);
        $clickToLoad = (float) ($durations['click_to_load'] ?? 0);
        $clickToFetch = (float) ($durations['click_to_fetch'] ?? 0);
        $fetchToTtfb = (float) ($durations['fetch_to_response_start'] ?? 0);
        $domToLoad = (float) ($durations['dom_to_load'] ?? 0);

        $merged = [
            'record_type' => 'e2e',
            'trace_id' => $client['trace_id'] ?? null,
            'logged_at' => now()->toIso8601String(),
            'slow' => $clickToLoad >= (int) config('request_debug.slow_threshold_ms'),
            'path' => $client['path'] ?? null,
            'clicked_at' => $client['clicked_at'] ?? null,
            'server_received_at' => $server['server_received_at'] ?? null,
            'server_finished_at' => $server['logged_at'] ?? null,
            'page_loaded_at' => $client['page_loaded_at'] ?? null,
            'durations_ms' => $durations,
            'server' => $server !== null ? [
                'duration_ms' => $serverMs,
                'database' => $server['database'] ?? null,
                'route' => $server['request']['route'] ?? null,
            ] : null,
            'client' => [
                'navigation_type' => $client['navigation_type'] ?? null,
                'slow_resources' => $client['slow_resources'] ?? [],
            ],
            'breakdown' => [
                '1_click_to_fetch' => [
                    'ms' => $clickToFetch,
                    'label' => 'Clique até o navegador iniciar a requisição',
                ],
                '2_fetch_to_ttfb' => [
                    'ms' => $fetchToTtfb,
                    'label' => 'Início da requisição até primeiro byte (TTFB)',
                    'server_ms' => $serverMs,
                    'network_overhead_ms' => round(max(0, $fetchToTtfb - $serverMs), 2),
                ],
                '3_download' => [
                    'ms' => $durations['response_start_to_end'] ?? 0,
                    'label' => 'Download do HTML',
                ],
                '4_dom_processing' => [
                    'ms' => $durations['response_end_to_dom'] ?? 0,
                    'label' => 'Parse DOM até DOMContentLoaded',
                ],
                '5_assets_and_load' => [
                    'ms' => $domToLoad,
                    'label' => 'DOMContentLoaded até load (CSS/JS/imagens)',
                ],
                'total_click_to_load' => [
                    'ms' => $clickToLoad,
                    'label' => 'Clique até load completo',
                ],
            ],
        ];

        $merged['likely_causes'] = RequestDebugE2eAnalyzer::likelyCauses($merged);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $client
     */
    public static function appendClientAndMerged(array $client): void
    {
        $client['record_type'] = 'client';
        RequestDebugLogger::append($client);

        $server = isset($client['trace_id'])
            ? self::findServerRecord((string) $client['trace_id'])
            : null;

        RequestDebugLogger::append(self::buildMergedRecord($client, $server));
    }
}
