<?php

namespace App\Http\Middleware;

use App\Support\RequestDebug\RequestDebugContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StartRequestDebug
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('request_debug.enabled') || $this->shouldIgnore($request)) {
            return $next($request);
        }

        RequestDebugContext::start($request);
        RequestDebugContext::milestone('middleware_start');

        return $next($request);
    }

    private function shouldIgnore(Request $request): bool
    {
        foreach (config('request_debug.ignore_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
