<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia o acesso de usuários autenticados que ainda usam a senha padrão.
 *
 * Permitidos enquanto must_change_password = true:
 *  - GET  /alterar-senha-obrigatoria   (password.force.change)
 *  - PUT  /alterar-senha-obrigatoria   (password.force.update)
 *  - POST /logout                       (logout)
 *
 * Qualquer outra rota é redirecionada para a tela de troca obrigatória.
 */
class EnsurePasswordWasChanged
{
    /**
     * Rotas (por nome) que permanecem acessíveis enquanto a troca é exigida.
     *
     * @var list<string>
     */
    private array $allowedRoutes = [
        'password.force.change',
        'password.force.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (bool) $user->must_change_password === true) {
            $currentRoute = optional($request->route())->getName();

            if (! in_array($currentRoute, $this->allowedRoutes, true)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'É necessário trocar a senha antes de continuar.',
                        'redirect' => route('password.force.change'),
                    ], 423);
                }

                return redirect()
                    ->route('password.force.change')
                    ->with('warning', 'Você precisa trocar sua senha temporária antes de continuar.');
            }
        }

        return $next($request);
    }
}
