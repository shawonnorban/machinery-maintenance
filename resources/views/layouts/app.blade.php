{{--
    The authenticated shell (Frontend 4).
    CoreUI 5 Free layout: sidebar, header, breadcrumb, content, footer.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-coreui-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('nav.dashboard')) — {{ config('app.name') }}</title>

    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2c3e50">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body data-company-id="{{ $tenant->companyIdOrNull() }}" data-locale="{{ app()->getLocale() }}">
    {{--
        Per-request context passed from Blade, not compiled into the bundle:
        it varies per user and per company (Handbook 5.2).
    --}}
    <script @cspnonce>
        window.App = @json($appJs);
    </script>

    <x-layout.support-banner />

    <x-layout.sidebar :menu="$menu" />

    <div class="wrapper d-flex flex-column min-vh-100">
        <x-layout.header :companies="$companies" :factories="$factories"
                         :unread-notifications="$unreadNotifications ?? 0"
                         :recent-notifications="$recentNotifications ?? null" />

        <div class="body flex-grow-1">
            {{-- Fluid, not container-lg: a capped, centred container left a wide empty
                 band down the right of every listing on a normal desktop. --}}
            <div class="container-fluid px-4">
                @hasSection('breadcrumb')
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb my-3">
                            @yield('breadcrumb')
                        </ol>
                    </nav>
                @endif

                <x-layout.flash />

                @yield('content')
            </div>
        </div>

        <footer class="footer px-4">
            <div>{{ config('app.name') }}</div>
            <div class="ms-auto small text-body-secondary">
                {{ $tenant->companyIdOrNull() ? ($companies->firstWhere('id', $tenant->companyIdOrNull())?->name ?? '') : '' }}
            </div>
        </footer>
    </div>

    {{-- Where live events surface. Bottom right and never blocking: somebody
         typing a reading into a work order must not have a breakdown alert
         steal their keystrokes (SRS 29, Frontend 8). --}}
    <div class="toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>

    @stack('scripts')
</body>
</html>
