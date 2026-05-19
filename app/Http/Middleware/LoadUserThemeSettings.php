<?php

namespace App\Http\Middleware;

use App\Support\RequestDebug\RequestDebugContext;
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

        RequestDebugContext::milestone('theme_middleware_start');

        $sessionUserId = $request->session()->get('theme_settings_user_id');
        $hasCachedTheme = $request->session()->has('theme_settings')
            && (int) $sessionUserId === (int) $user->getKey();

        if ($hasCachedTheme) {
            RequestDebugContext::milestone('theme_session_hit');
        } else {
            $user->refresh();
            $request->session()->put('theme_settings', $user->themeSettings());
            $request->session()->put('theme_settings_user_id', $user->getKey());
            RequestDebugContext::milestone('theme_user_refresh', [
                'reason' => $sessionUserId === null ? 'no_session' : 'user_mismatch',
            ]);
        }

        RequestDebugContext::milestone('theme_middleware_end');

        return $next($request);
    }
}
