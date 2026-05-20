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
        $belongsToCurrentUser = (int) $sessionUserId === (int) $user->getKey();
        $user->refresh();
        $settingsFromDatabase = $user->themeSettings();
        $sessionSettings = $request->session()->get('theme_settings');
        $sessionIsCurrent = $belongsToCurrentUser
            && is_array($sessionSettings)
            && $sessionSettings === $settingsFromDatabase;

        if ($sessionIsCurrent) {
            RequestDebugContext::milestone('theme_session_hit');
        } else {
            $request->session()->put('theme_settings', $settingsFromDatabase);
            $request->session()->put('theme_settings_user_id', $user->getKey());
            RequestDebugContext::milestone('theme_user_refresh', [
                'reason' => ! $belongsToCurrentUser
                    ? ($sessionUserId === null ? 'no_session' : 'user_mismatch')
                    : 'stale_session',
            ]);
        }

        RequestDebugContext::milestone('theme_middleware_end');

        return $next($request);
    }
}
