@php
    $sessionThemeSettings = session('theme_settings');
    $sessionThemeSettingsBelongsToUser = auth()->check()
        && session('theme_settings_user_id') === auth()->id()
        && is_array($sessionThemeSettings);

    $themeSettings = ($sessionThemeSettingsBelongsToUser ? $sessionThemeSettings : null)
        ?? auth()->user()?->themeSettings()
        ?? \App\Models\User::defaultThemeSettings();
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-bs-theme="{{ $themeSettings['data-bs-theme'] }}"
    data-layout-mode="{{ $themeSettings['data-layout-mode'] }}"
    data-topbar-color="{{ $themeSettings['data-topbar-color'] }}"
    data-menu-color="{{ $themeSettings['data-menu-color'] }}"
    data-sidenav-size="{{ $themeSettings['data-sidenav-size'] }}"
    data-theme-settings-source="{{ auth()->check() ? 'server' : 'guest' }}"
    data-theme-settings-user-id="{{ auth()->id() ?? '' }}"
    data-theme-settings-url="{{ auth()->check() ? route('theme-settings.update') : '' }}"
>

<head>
    <script>
        window.themeSettingsFromServer = @json($themeSettings);
        window.currentThemeSettings = window.themeSettingsFromServer;
    </script>
    @include('layouts.partials.head')
    <style>
        body.modulos-hub-body .modulos-hub-wrapper .app-topbar,
        body.modulos-hub-body .modulos-hub-wrapper .page-content {
            margin-inline-start: 0 !important;
            margin-left: 0 !important;
        }
    </style>
</head>

<body class="modulos-hub-body">
    <div class="wrapper modulos-hub-wrapper">

        @include('layouts.partials.topbar-modulos')

        <div class="page-content">
            <div class="page-container">
                @yield('content')
            </div>

            @include('layouts.partials.footer')
        </div>

    </div>

    @include('layouts.partials.theme-settings')

    <x-admin.confirm-modal />

    @include('layouts.partials.scripts')
</body>

</html>
