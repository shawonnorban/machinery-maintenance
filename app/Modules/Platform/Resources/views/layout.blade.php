{{-- The platform shell (SRS §5).

     The same structure as the application — sidebar, sticky header, content,
     footer — so it is one product rather than two, and so every rule already
     written for `.sidebar`, `.wrapper` and `.header` applies here without
     being written twice.

     What stays different is the identity: an amber brand, an amber rule under
     the header, and the word "platform staff" in the top corner. Somebody who
     has just stepped out of a support session should never have to work out
     which side of the tenancy they are standing on, and there is still no
     company switcher and no factory scope here because neither means anything
     above the tenancy. --}}
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
<body class="platform-shell">
    <div class="sidebar sidebar-dark sidebar-fixed" id="sidebar">
        <div class="sidebar-header border-bottom">
            <a class="sidebar-brand px-3 fw-semibold text-truncate text-decoration-none"
               href="{{ route('platform.tenants') }}">
                <span class="platform-brand-mark">◆</span>
                {{ __('platform.platform') }}
            </a>
        </div>

        <ul class="sidebar-nav" data-coreui="navigation">
            @php
                // Only destinations that exist. A sidebar entry pointing at a
                // page nobody built is worse than a short sidebar.
                $items = [
                    ['route' => 'platform.tenants', 'icon' => 'building', 'label' => 'platform.tenants'],
                    ['route' => 'platform.finance', 'icon' => 'wallet', 'label' => 'platform.finance'],
                    ['route' => 'platform.support', 'icon' => 'lock-locked', 'label' => 'platform.support_access'],
                    ['route' => 'platform.tickets', 'icon' => 'envelope-letter', 'label' => 'platform.support_ticket'],
                    ['route' => 'platform.notifications', 'icon' => 'bell', 'label' => 'notification.notifications'],
                ];
            @endphp

            @foreach ($items as $item)
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}"
                       href="{{ route($item['route']) }}">
                        <span class="nav-icon">
                            <i class="cil-{{ $item['icon'] }}" aria-hidden="true"></i>
                        </span>
                        {{ __($item['label']) }}

                        @if ($item['route'] === 'platform.support' && $openGrantCount > 0)
                            {{-- The one count worth carrying in the nav:
                                 somebody is inside a customer's data now. --}}
                            <span class="badge bg-danger ms-auto">{{ $openGrantCount }}</span>
                        @endif

                        @if ($item['route'] === 'platform.tickets' && $openTicketCount > 0)
                            <span class="badge bg-danger ms-auto">{{ $openTicketCount }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="sidebar-footer border-top">
            <div class="platform-who px-3 py-2">
                <span class="platform-who-name">{{ auth()->user()->name }}</span>
                <span class="platform-who-role">{{ __('platform.staff') }}</span>
            </div>
        </div>
    </div>

    <div class="wrapper d-flex flex-column min-vh-100">
        <header class="header header-sticky mb-4 border-bottom platform-header">
            <div class="container-fluid px-4">
                <button class="header-toggler d-lg-none" type="button" data-sidebar-toggle
                        aria-label="{{ __('common.toggle_navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <ul class="header-nav ms-auto align-items-center">
                    @include('platform::partials._bell')

                    <li class="nav-item dropdown">
                        <a class="nav-link" href="#" data-coreui-toggle="dropdown"
                           role="button" aria-expanded="false">
                            {{ strtoupper(app()->getLocale()) }}
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            @foreach (['en' => 'English', 'bn' => 'বাংলা'] as $code => $name)
                                <form method="POST" action="{{ route('app.locale') }}">
                                    @csrf
                                    <input type="hidden" name="locale" value="{{ $code }}">
                                    <button class="dropdown-item" type="submit">{{ $name }}</button>
                                </form>
                            @endforeach
                        </div>
                    </li>

                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">
                                {{ __('common.sign_out') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <div class="body flex-grow-1">
            <div class="container-fluid px-4">
                <x-layout.flash />

                @yield('content')
            </div>
        </div>

        <footer class="footer px-4">
            {{-- Restated on every screen, because it is the rule most easily
                 forgotten by whoever is trying to help a customer quickly. --}}
            <div class="small text-body-secondary">{{ __('platform.no_data_access_note') }}</div>
        </footer>
    </div>
</body>
</html>
