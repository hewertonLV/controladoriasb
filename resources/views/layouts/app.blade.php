<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

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
