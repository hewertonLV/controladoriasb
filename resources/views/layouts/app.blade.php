@php
    $themeSettings = auth()->user()?->themeSettings() ?? \App\Models\User::defaultThemeSettings();
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
    data-theme-settings-url="{{ auth()->check() ? route('theme-settings.update') : '' }}"
>

<head>
    @include('layouts.partials.head')
</head>

<body>
    <div class="wrapper">

        @include('layouts.partials.sidebar')

        @include('layouts.partials.topbar')

        <div class="page-content">
            <div class="page-container">
                @yield('content')
            </div>

            @include('layouts.partials.footer')
        </div>

    </div>

    @include('layouts.partials.theme-settings')

    @include('layouts.partials.scripts')
</body>

</html>
