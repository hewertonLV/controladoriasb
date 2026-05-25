<?php

namespace App\Http\Middleware;

use App\Support\RequestDebug\RequestDebugAnalyzer;
use App\Support\RequestDebug\RequestDebugContext;
use App\Support\RequestDebugLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinishRequestDebug
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! RequestDebugContext::active()) {
            return $next($request);
        }

        RequestDebugContext::milestone('before_controller_stack');

        $response = $next($request);

        RequestDebugContext::milestone('response_ready', [
            'status' => $response->getStatusCode(),
        ]);

        $durationMs = RequestDebugContext::elapsedMs();
        $slowThreshold = (int) config('request_debug.slow_threshold_ms');

        $record = [
            'record_type' => 'server',
            'trace_id' => RequestDebugContext::traceId(),
            'server_received_at' => RequestDebugContext::serverReceivedAt(),
            'logged_at' => now()->toIso8601String(),
            'duration_ms' => $durationMs,
            'slow' => $durationMs >= $slowThreshold,
            'request' => $this->requestPayload($request),
            'response' => [
                'status' => $response->getStatusCode(),
            ],
            'memory' => [
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ],
            'timeline' => RequestDebugAnalyzer::buildTimeline(),
            'database' => $this->databaseSummary(),
        ];

        $record['likely_causes'] = RequestDebugAnalyzer::likelyCauses($record);

        RequestDebugLogger::append($record);
        RequestDebugContext::reset();

        if ($record['trace_id'] ?? null) {
            return $this->expireTraceCookie($response);
        }

        return $response;
    }

    /**
     * StreamedResponse (downloads) não usa ResponseTrait::withoutCookie().
     */
    private function expireTraceCookie(Response $response): Response
    {
        if (method_exists($response, 'withoutCookie')) {
            return $response->withoutCookie('rd_trace');
        }

        if (function_exists('cookie')) {
            $response->headers->setCookie(cookie('rd_trace', null, -2628000));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(Request $request): array
    {
        return [
            'method' => $request->method(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'action' => $request->route()?->getActionName(),
            'middleware' => $request->route()?->gatherMiddleware() ?? [],
            'query' => $request->query(),
            'input' => $this->redactedInput($request),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->getKey(),
            'ajax' => $request->ajax(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function redactedInput(Request $request): array
    {
        $keys = config('request_debug.redact_input_keys', []);

        return collect($request->except($keys))
            ->reject(fn ($value) => $value instanceof \Illuminate\Http\UploadedFile)
            ->map(function ($value) {
                if (is_string($value) && strlen($value) > 500) {
                    return substr($value, 0, 500).'…';
                }

                return $value;
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseSummary(): array
    {
        if (! config('request_debug.log_queries')) {
            return ['enabled' => false];
        }

        $queries = RequestDebugContext::queries();
        $maxStored = (int) config('request_debug.max_queries_stored');
        $slowQueryMs = (float) config('request_debug.slow_query_ms', 100);

        $totalTimeMs = 0.0;
        foreach ($queries as $query) {
            $totalTimeMs += (float) $query['time_ms'];
        }

        $sorted = $queries;
        usort($sorted, fn (array $a, array $b) => $b['time_ms'] <=> $a['time_ms']);

        $slowest = array_slice($sorted, 0, 10);
        $overThreshold = array_values(array_filter(
            $sorted,
            fn (array $q) => (float) $q['time_ms'] >= $slowQueryMs,
        ));
        $overThreshold = array_slice($overThreshold, 0, 15);

        $stored = array_slice($sorted, 0, $maxStored);
        if (count($sorted) > $maxStored) {
            $stored[] = ['truncated' => (count($sorted) - $maxStored).' queries omitted'];
        }

        return [
            'query_count' => count($queries),
            'time_ms' => round($totalTimeMs, 2),
            'slowest' => $slowest,
            'over_threshold_ms' => $overThreshold,
            'queries' => $stored,
        ];
    }
}
