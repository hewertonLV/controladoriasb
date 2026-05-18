<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeSettingsController extends Controller
{
    /**
     * Persist authenticated user's theme preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $settings = $request->validate([
            'data-bs-theme' => ['nullable', Rule::in(['light', 'dark'])],
            'data-layout-mode' => ['nullable', Rule::in(['fluid', 'detached'])],
            'data-topbar-color' => ['nullable', Rule::in(['light', 'dark', 'brand'])],
            'data-menu-color' => ['nullable', Rule::in(['light', 'dark', 'brand'])],
            'data-sidenav-size' => ['nullable', Rule::in([
                'default',
                'compact',
                'condensed',
                'sm-hover',
                'sm-hover-active',
                'full',
                'fullscreen',
            ])],
        ]);

        $settings = array_filter($settings, static fn ($value): bool => $value !== null);
        $user = $request->user();

        $user->forceFill([
            'theme_settings' => array_merge($user->themeSettings(), $settings),
        ])->save();

        $themeSettings = $user->fresh()->themeSettings();
        $request->session()->put('theme_settings', $themeSettings);
        $request->session()->put('theme_settings_user_id', $user->getKey());

        return response()->json([
            'theme_settings' => $themeSettings,
        ]);
    }
}
