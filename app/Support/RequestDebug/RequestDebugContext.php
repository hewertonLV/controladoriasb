<?php

namespace App\Support\RequestDebug;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestDebugContext
{
    private static ?float $startedAt = null;

    private static ?string $serverReceivedAt = null;

    private static ?string $traceId = null;

    private static ?Request $request = null;

    /** @var list<array{name: string, at_ms: float, meta: array<string, mixed>|null}> */
    private static array $milestones = [];

    /** @var list<array{time_ms: float, sql: string, connection: string}> */
    private static array $queries = [];

    private static bool $listenerRegistered = false;

    public static function start(Request $request): void
    {
        self::reset();
        self::$startedAt = microtime(true);
        self::$serverReceivedAt = now()->toIso8601String();
        self::$request = $request;
        self::$traceId = is_string($request->cookie('rd_trace'))
            && preg_match('/^[0-9a-f-]{36}$/i', (string) $request->cookie('rd_trace'))
            ? (string) $request->cookie('rd_trace')
            : null;
        self::milestone('request_start');

        if (config('request_debug.log_queries')) {
            self::registerQueryListener();
        }
    }

    public static function active(): bool
    {
        return self::$startedAt !== null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function milestone(string $name, array $meta = []): void
    {
        if (! self::active()) {
            return;
        }

        self::$milestones[] = [
            'name' => $name,
            'at_ms' => self::elapsedMs(),
            'meta' => $meta !== [] ? $meta : null,
        ];
    }

    public static function elapsedMs(): float
    {
        if (self::$startedAt === null) {
            return 0.0;
        }

        return round((microtime(true) - self::$startedAt) * 1000, 2);
    }

    public static function request(): ?Request
    {
        return self::$request;
    }

    public static function traceId(): ?string
    {
        return self::$traceId;
    }

    public static function serverReceivedAt(): ?string
    {
        return self::$serverReceivedAt;
    }

    /**
     * @return list<array{name: string, at_ms: float, meta: array<string, mixed>|null}>
     */
    public static function milestones(): array
    {
        return self::$milestones;
    }

    /**
     * @return list<array{time_ms: float, sql: string, connection: string}>
     */
    public static function queries(): array
    {
        return self::$queries;
    }

    public static function reset(): void
    {
        self::$startedAt = null;
        self::$serverReceivedAt = null;
        self::$traceId = null;
        self::$request = null;
        self::$milestones = [];
        self::$queries = [];
    }

    private static function registerQueryListener(): void
    {
        if (self::$listenerRegistered) {
            return;
        }

        DB::listen(static function (QueryExecuted $query): void {
            if (! self::active()) {
                return;
            }

            $sqlMax = (int) config('request_debug.sql_max_length');
            $sql = $query->sql;
            if (strlen($sql) > $sqlMax) {
                $sql = substr($sql, 0, $sqlMax).'…';
            }

            self::$queries[] = [
                'time_ms' => round((float) $query->time, 2),
                'sql' => $sql,
                'connection' => $query->connectionName,
            ];
        });

        self::$listenerRegistered = true;
    }
}
