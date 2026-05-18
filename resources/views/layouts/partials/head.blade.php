<meta charset="utf-8" />
<title>@yield('title', 'Dashboard') | {{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta content="Sistema {{ config('app.name') }}" name="description" />
<meta content="{{ config('app.name') }}" name="author" />

<link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}">

<link href="{{ asset('assets/css/vendor.min.css') }}" rel="stylesheet" type="text/css" />

<link href="{{ asset('assets/css/app.min.css') }}" rel="stylesheet" type="text/css" id="app-style" />

<link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

<link href="{{ asset('assets/css/theme-dynamic.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/css/admin-tables.css') }}" rel="stylesheet" type="text/css" />

<script src="{{ asset('assets/js/config.js') }}"></script>

@stack('head')
