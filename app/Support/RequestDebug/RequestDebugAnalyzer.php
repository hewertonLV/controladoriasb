<?php

namespace App\Support\RequestDebug;

class RequestDebugAnalyzer
{
    /**
     * @param  array<string, mixed>  $record
     * @return list<array{code: string, severity: string, message: string, evidence?: array<string, mixed>}>
     */
    public static function likelyCauses(array $record): array
    {
        $causes = [];

        $durationMs = (float) ($record['duration_ms'] ?? 0);
        $slowThreshold = (int) config('request_debug.slow_threshold_ms');
        $isSlow = (bool) ($record['slow'] ?? false);

        /** @var array<string, mixed> $database */
        $database = is_array($record['database'] ?? null) ? $record['database'] : [];
        $dbTimeMs = (float) ($database['time_ms'] ?? 0);
        $queryCount = (int) ($database['query_count'] ?? 0);

        /** @var array<string, mixed> $request */
        $request = is_array($record['request'] ?? null) ? $record['request'] : [];
        /** @var array<string, mixed> $query */
        $query = is_array($request['query'] ?? null) ? $request['query'] : [];

        if ($dbTimeMs > 0 && $durationMs > 0 && ($dbTimeMs / $durationMs) >= 0.5) {
            $causes[] = [
                'code' => 'database_bound',
                'severity' => 'high',
                'message' => sprintf(
                    'Mais de 50%% do tempo (%s ms de %s ms) foi em consultas SQL.',
                    $dbTimeMs,
                    $durationMs,
                ),
                'evidence' => [
                    'db_time_ms' => $dbTimeMs,
                    'db_share_pct' => round(($dbTimeMs / $durationMs) * 100, 1),
                ],
            ];
        }

        if ($queryCount >= (int) config('request_debug.high_query_count', 30)) {
            $causes[] = [
                'code' => 'high_query_count',
                'severity' => 'high',
                'message' => "Muitas queries na mesma requisição ({$queryCount}). Suspeita de N+1 ou permissões repetidas.",
                'evidence' => ['query_count' => $queryCount],
            ];
        }

        foreach (self::repeatedQueryPatterns($database) as $repeat) {
            $causes[] = [
                'code' => 'repeated_query_pattern',
                'severity' => 'medium',
                'message' => "Mesmo padrão de SQL executado {$repeat['count']} vezes (possível N+1).",
                'evidence' => $repeat,
            ];
        }

        if (($query['per_page'] ?? null) === 'all') {
            $causes[] = [
                'code' => 'unpaginated_list',
                'severity' => 'high',
                'message' => 'Listagem com per_page=all carrega todos os registros de uma vez.',
                'evidence' => ['per_page' => 'all'],
            ];
        }

        if (trim((string) ($query['search'] ?? '')) !== '') {
            $causes[] = [
                'code' => 'active_search_filter',
                'severity' => 'medium',
                'message' => 'Busca com LIKE em várias colunas pode causar full scan.',
                'evidence' => ['search' => $query['search']],
            ];
        }

        /** @var list<array<string, mixed>> $timeline */
        $timeline = is_array($record['timeline'] ?? null) ? $record['timeline'] : [];
        foreach ($timeline as $step) {
            if (($step['name'] ?? '') === 'theme_user_refresh') {
                $causes[] = [
                    'code' => 'theme_middleware_db_refresh',
                    'severity' => 'medium',
                    'message' => 'Middleware de tema recarregou o usuário do banco (refresh).',
                    'evidence' => is_array($step['meta'] ?? null) ? $step['meta'] : [],
                ];
            }
        }

        $peakMb = (float) (($record['memory']['peak_mb'] ?? 0));
        if ($peakMb >= (float) config('request_debug.high_memory_mb', 128)) {
            $causes[] = [
                'code' => 'high_memory',
                'severity' => 'medium',
                'message' => "Pico de memória alto ({$peakMb} MB).",
                'evidence' => ['peak_mb' => $peakMb],
            ];
        }

        $outsideDbMs = round(max(0, $durationMs - $dbTimeMs), 2);
        if ($isSlow && $outsideDbMs >= 500 && ($dbTimeMs / max($durationMs, 1)) < 0.4) {
            $causes[] = [
                'code' => 'time_outside_database',
                'severity' => 'medium',
                'message' => sprintf(
                    '%s ms fora do SQL — provável renderização Blade, filas, I/O de sessão ou assets.',
                    $outsideDbMs,
                ),
                'evidence' => ['outside_db_ms' => $outsideDbMs],
            ];
        }

        if ($isSlow && $causes === []) {
            $causes[] = [
                'code' => 'slow_without_clear_signal',
                'severity' => 'low',
                'message' => 'Requisição lenta sem padrão óbvio; revise timeline e database.slowest.',
                'evidence' => ['duration_ms' => $durationMs, 'slow_threshold_ms' => $slowThreshold],
            ];
        }

        return $causes;
    }

    /**
     * @param  array<string, mixed>  $database
     * @return list<array{count: int, sql_sample: string}>
     */
    private static function repeatedQueryPatterns(array $database): array
    {
        $minRepeats = (int) config('request_debug.repeated_query_min', 5);
        /** @var list<array{sql?: string}> $queries */
        $queries = is_array($database['queries'] ?? null) ? $database['queries'] : [];

        $counts = [];
        foreach ($queries as $entry) {
            if (! is_array($entry) || ! isset($entry['sql'])) {
                continue;
            }
            $fingerprint = preg_replace('/\b\d+\b/', '?', (string) $entry['sql']) ?? (string) $entry['sql'];
            $counts[$fingerprint] = ($counts[$fingerprint] ?? 0) + 1;
        }

        $repeats = [];
        foreach ($counts as $sql => $count) {
            if ($count >= $minRepeats) {
                $repeats[] = [
                    'count' => $count,
                    'sql_sample' => strlen($sql) > 200 ? substr($sql, 0, 200).'…' : $sql,
                ];
            }
        }

        usort($repeats, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return array_slice($repeats, 0, 5);
    }

    /**
     * @return list<array{name: string, at_ms: float, delta_ms: float|null, meta: array<string, mixed>|null}>
     */
    public static function buildTimeline(): array
    {
        $milestones = RequestDebugContext::milestones();
        $timeline = [];
        $previousAt = 0.0;

        foreach ($milestones as $milestone) {
            $atMs = (float) $milestone['at_ms'];
            $timeline[] = [
                'name' => $milestone['name'],
                'at_ms' => $atMs,
                'delta_ms' => round($atMs - $previousAt, 2),
                'meta' => $milestone['meta'],
            ];
            $previousAt = $atMs;
        }

        $totalMs = RequestDebugContext::elapsedMs();
        if ($timeline !== []) {
            $lastAt = (float) end($timeline)['at_ms'];
            $remaining = round($totalMs - $lastAt, 2);
            if ($remaining > 1) {
                $timeline[] = [
                    'name' => 'after_last_milestone',
                    'at_ms' => $totalMs,
                    'delta_ms' => $remaining,
                    'meta' => ['hint' => 'controller, views, response, middleware final'],
                ];
            }
        }

        return $timeline;
    }
}
