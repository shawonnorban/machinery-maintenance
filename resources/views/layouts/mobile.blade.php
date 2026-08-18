{{-- Technician screens (Frontend 6.2). No sidebar, no breadcrumb, no header:
     used one-handed, standing next to a running machine. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-coreui-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — {{ config('app.name') }}</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js', 'resources/js/mobile.js'])
</head>
<body>
    <script>
        window.App = @json($appJs);
    </script>

    <div class="mobile-shell">
        <div class="mobile-topbar border-bottom px-3 py-2 d-flex align-items-center gap-3">
            @yield('topbar')
        </div>

        <div class="mobile-body">
            {{-- Present here too, not only on the desktop layout. A refused
                 answer that produces no visible message is worse than an error:
                 the technician taps Record, the screen comes back unchanged, and
                 they walk away believing a failed safety check was logged. --}}
            <div class="px-3 pt-3">
                <x-layout.flash />
            </div>

            @yield('content')
        </div>

        {{-- Sync state is always visible. A technician must never wonder
             whether their work was recorded (Frontend 6.2 rule 5). --}}
        <div class="mobile-footbar border-top px-3 py-2">
            <span class="sync-state" data-state="synced" id="sync-state">✓ {{ __('common.connection_live') }}</span>
        </div>
    </div>
</body>
</html>
