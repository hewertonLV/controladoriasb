<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('layouts.partials.head')
</head>

<body>

    <div class="auth-bg d-flex min-vh-100 justify-content-center align-items-center">
        <div class="row g-0 justify-content-center w-100 m-xxl-5 px-xxl-4 m-3">
            <div class="col-xl-4 col-lg-5 col-md-6">
                <div class="card overflow-hidden text-center h-100 p-xxl-4 p-3 mb-0">
                    <a href="{{ url('/') }}" class="auth-brand mb-4">
                        <img src="{{ asset('assets/images/logo-dark.png') }}" alt="dark logo" height="26" class="logo-dark">
                        <img src="{{ asset('assets/images/logo.png') }}" alt="logo light" height="26" class="logo-light">
                    </a>

                    @yield('content')

                    <p class="mt-auto mb-0">
                        <script>document.write(new Date().getFullYear())</script> © {{ config('app.name') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/js/vendor.min.js') }}"></script>
    <script src="{{ asset('assets/js/password-toggle.js') }}"></script>
    @stack('scripts')
</body>

</html>
