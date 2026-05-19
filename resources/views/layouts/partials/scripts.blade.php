@if (config('request_debug.enabled') && config('request_debug.client_tracking'))
    <script>
        window.__REQUEST_DEBUG__ = {
            reportUrl: @json(route('request-debug.client-report')),
            csrf: @json(csrf_token()),
        };
    </script>
    <script src="{{ asset('assets/js/request-debug-client.js') }}"></script>
@endif

<script src="{{ asset('assets/js/vendor.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script src="{{ asset('assets/js/theme-settings-persistence.js') }}"></script>
<script src="{{ asset('assets/js/form-submit-guard.js') }}"></script>
<script src="{{ asset('assets/js/admin-confirm.js') }}"></script>
<script src="{{ asset('assets/js/interaction-resilience.js') }}"></script>

@stack('scripts')
