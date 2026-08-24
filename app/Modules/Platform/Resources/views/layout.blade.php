{{-- The platform shell (SRS 5).

     Deliberately not the tenant shell. There is no company switcher, no
     factory scope, no sidebar of maintenance screens — because none of them
     mean anything here. Making it look different from the customer's
     application is itself a safety feature: somebody should never be in doubt
     about which side of the tenancy they are standing on. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-coreui-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — {{ __('platform.platform') }}</title>

    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-dark bg-dark px-3">
        <a class="navbar-brand" href="{{ route('platform.tenants') }}">{{ __('platform.platform') }}</a>

        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white-50 small">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-sm btn-outline-light">{{ __('common.sign_out') }}</button>
            </form>
        </div>
    </nav>

    <main class="container-fluid px-4 py-4">
        <x-layout.flash />

        @yield('content')
    </main>
</body>
</html>
