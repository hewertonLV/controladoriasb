<?php

namespace App\Support\RequestDebug;

class RequestDebugE2eAnalyzer
{
    /**
     * @param  array<string, mixed>  $merged
     * @return list<array{code: string, severity: string, message: string, evidence?: array<string, mixed>}>
     */
    public static function likelyCauses(array $merged): array
    {
        $causes = [];
        $threshold = (int) config('request_debug.slow_threshold_ms');

        /** @var array<string, mixed> $durations */
        $durations = is_array($merged['durations_ms'] ?? null) ? $merged['durations_ms'] : [];
        $clickToLoad = (float) ($durations['click_to_load'] ?? 0);
        $clickToFetch = (float) ($durations['click_to_fetch'] ?? 0);
        $fetchToTtfb = (float) ($durations['fetch_to_response_start'] ?? 0);
        $domToLoad = (float) ($durations['dom_to_load'] ?? 0);
        $responseEndToDom = (float) ($durations['response_end_to_dom'] ?? 0);

        /** @var array<string, mixed>|null $server */
        $server = is_array($merged['server'] ?? null) ? $merged['server'] : null;
        $serverMs = (float) ($server['duration_ms'] ?? 0);

        if ($clickToLoad < $threshold) {
            return [];
        }

        if ($serverMs > 0 && $clickToLoad > 0 && ($serverMs / $clickToLoad) < 0.15 && $clickToLoad >= $threshold) {
            $causes[] = [
                'code' => 'client_or_network_bound',
                'severity' => 'high',
                'message' => sprintf(
                    'Servidor respondeu em %s ms, mas do clique ao load foram %s ms — gargalo no navegador, rede ou assets.',
                    $serverMs,
                    $clickToLoad,
                ),
                'evidence' => ['server_ms' => $serverMs, 'click_to_load_ms' => $clickToLoad],
            ];
        }

        if ($serverMs >= 500) {
            $causes[] = [
                'code' => 'server_slow',
                'severity' => 'high',
                'message' => "Processamento PHP/Laravel lento ({$serverMs} ms).",
                'evidence' => ['server_ms' => $serverMs],
            ];
        }

        if ($clickToFetch >= 300) {
            $causes[] = [
                'code' => 'delay_before_request',
                'severity' => 'medium',
                'message' => sprintf(
                    '%s ms entre o clique e o início da requisição — fila do navegador, JS bloqueando ou link lento.',
                    $clickToFetch,
                ),
                'evidence' => ['click_to_fetch_ms' => $clickToFetch],
            ];
        }

        $networkOverhead = round(max(0, $fetchToTtfb - $serverMs), 2);
        if ($networkOverhead >= 200) {
            $causes[] = [
                'code' => 'network_or_session_latency',
                'severity' => 'medium',
                'message' => sprintf(
                    'TTFB (%s ms) muito acima do tempo de servidor (%s ms) — rede, Docker, sessão em DB ou proxy.',
                    $fetchToTtfb,
                    $serverMs,
                ),
                'evidence' => ['fetch_to_ttfb_ms' => $fetchToTtfb, 'server_ms' => $serverMs],
            ];
        }

        if ($domToLoad >= 800) {
            $causes[] = [
                'code' => 'heavy_assets_after_dom',
                'severity' => 'high',
                'message' => sprintf(
                    '%s ms entre DOMContentLoaded e load — CSS/JS/imagens pesados ou bloqueantes.',
                    $domToLoad,
                ),
                'evidence' => ['dom_to_load_ms' => $domToLoad],
            ];
        }

        if ($responseEndToDom >= 500) {
            $causes[] = [
                'code' => 'slow_dom_parse',
                'severity' => 'medium',
                'message' => sprintf(
                    '%s ms para processar HTML até DOMContentLoaded — DOM grande ou scripts síncronos.',
                    $responseEndToDom,
                ),
                'evidence' => ['response_end_to_dom_ms' => $responseEndToDom],
            ];
        }

        /** @var list<array<string, mixed>> $slowResources */
        $slowResources = is_array($merged['client']['slow_resources'] ?? null)
            ? $merged['client']['slow_resources']
            : [];

        if ($slowResources !== []) {
            $causes[] = [
                'code' => 'slow_static_resources',
                'severity' => 'medium',
                'message' => 'Recursos estáticos lentos detectados (veja client.slow_resources).',
                'evidence' => ['resources' => array_slice($slowResources, 0, 5)],
            ];
        }

        if ($causes === []) {
            $causes[] = [
                'code' => 'e2e_slow_uncategorized',
                'severity' => 'low',
                'message' => 'Navegação lenta; revise breakdown por fase.',
                'evidence' => ['click_to_load_ms' => $clickToLoad],
            ];
        }

        return $causes;
    }
}
