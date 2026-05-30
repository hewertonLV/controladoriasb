@php
    use App\Enums\AppModulo;

    $sessionThemeSettings = session('theme_settings');
    $sessionThemeSettingsBelongsToUser = auth()->check()
        && session('theme_settings_user_id') === auth()->id()
        && is_array($sessionThemeSettings);

    $themeSettings = ($sessionThemeSettingsBelongsToUser ? $sessionThemeSettings : null)
        ?? auth()->user()?->themeSettings()
        ?? \App\Models\User::defaultThemeSettings();

    $exibirSidebar = $exibirSidebarAdministrativa ?? true;
    $moduloCaptacaoAtivo = ($moduloAtivo ?? AppModulo::tryFromSession())?->usaTopbarModuloCaptacao() ?? false;
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
    @if (! $exibirSidebar)
        <style>
            body.modulo-operacional .app-topbar,
            body.modulo-operacional .page-content {
                margin-inline-start: 0 !important;
                margin-left: 0 !important;
            }

            body.modulo-captacao .topbar-menu-modulo-captacao {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
                align-items: center;
                gap: 0.5rem;
            }

            body.modulo-captacao .topbar-captacao-start {
                justify-self: start;
            }

            body.modulo-captacao .topbar-captacao-center {
                justify-self: center;
                max-width: 100%;
            }

            body.modulo-captacao .topbar-captacao-end {
                justify-self: end;
            }

            @media (max-width: 767.98px) {
                body.modulo-captacao .topbar-menu-modulo-captacao {
                    grid-template-columns: 1fr auto;
                    grid-template-rows: auto auto;
                }

                body.modulo-captacao .topbar-captacao-center {
                    grid-column: 1 / -1;
                    order: -1;
                }
            }
        </style>
    @endif
</head>

<body @class([
    'modulo-operacional' => ! $exibirSidebar,
    'modulo-captacao' => $moduloCaptacaoAtivo,
])>
    <div class="wrapper">

        @if ($exibirSidebar)
            @include('layouts.partials.sidebar')
        @endif

        @include('layouts.partials.topbar')

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
