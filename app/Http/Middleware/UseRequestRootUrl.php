<?php

namespace App\Http\Middleware;

use App\Support\DynamicAppUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usa o host/porta da requisição atual como URL base da aplicação.
 * Evita depender de IP fixo em APP_URL quando o servidor muda de rede.
 */
class UseRequestRootUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() !== '') {
            DynamicAppUrl::apply($request);
        }

        return $next($request);
    }
}
