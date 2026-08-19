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

    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body data-company-id="{{ $tenant->companyIdOrNull() }}" data-locale="{{ app()->getLocale() }}">
    {{--
        Per-request context passed from Blade, not compiled into the bundle:
        it varies per user and per company (Handbook 5.2).
    --}}
    <script>
        window.App = @json($appJs);
    </script>

    <x-layout.sidebar :menu="$menu" />

    <div class="wrapper d-flex flex-column min-vh-100">
        <x-layout.header :companies="$companies" :factories="$factories"
                         :unread-notifications="$unreadNotifications ?? 0" />

        <div class="body flex-grow-1">
            <div class="container-lg px-4">
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

    @stack('scripts')
</body>
</html>
