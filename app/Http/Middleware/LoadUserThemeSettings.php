<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoadUserThemeSettings
{
    /**
     * Ensure the authenticated user's theme preferences are cached in session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $request->session()->put('theme_settings', $user->refresh()->themeSettings());
        $request->session()->put('theme_settings_user_id', $user->getKey());

        return $next($request);
    }
}
