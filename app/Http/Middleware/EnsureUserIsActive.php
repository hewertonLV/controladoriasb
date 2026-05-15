<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que usuários autenticados só permaneçam logados enquanto
 * estiverem ativos. Caso a coluna users.ativo passe para false enquanto
 * a sessão já estiver aberta, o usuário é deslogado no próximo request.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (bool) ($user->ativo ?? true) === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Usuário desativado. Entre em contato com o administrador.',
                ], 403);
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Usuário desativado. Entre em contato com o administrador.']);
        }

        return $next($request);
    }
}
