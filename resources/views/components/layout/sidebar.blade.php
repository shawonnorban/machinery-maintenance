@props(['menu'])

{{-- Rendered from a permission-filtered definition (Frontend 4.1). An item
     the user cannot use is not rendered at all. Hiding a link is usability,
     not security; the route still enforces its own permission. --}}
<div class="sidebar sidebar-dark sidebar-fixed" id="sidebar">
    <div class="sidebar-header border-bottom">
        <div class="sidebar-brand px-3 fw-semibold text-truncate">
            {{ config('app.name') }}
        </div>
    </div>

    <ul class="sidebar-nav" data-coreui="navigation">
        @foreach ($menu as $item)
            @if (isset($item['children']))
                @php
                    // A collapsed group whose child is the current page gives
                    // the reader no idea where they are, so the parent carries
                    // the state too and the group opens on it.
                    $childActive = collect($item['children'])
                        ->contains(fn (array $child) => request()->routeIs($child['route']));
                @endphp

                <li class="nav-group {{ $childActive ? 'show' : '' }}">
                    <a class="nav-link nav-group-toggle {{ $childActive ? 'active' : '' }}" href="#">
                        {{-- The icon comes from the menu definition. It used to
                             be an empty span, which left the reserved gap and
                             no icon in it. --}}
                        <span class="nav-icon">
                            <i class="cil-{{ $item['icon'] ?? 'circle' }}" aria-hidden="true"></i>
                        </span>
                        {{ __($item['label']) }}
                    </a>
                    <ul class="nav-group-items">
                        @foreach ($item['children'] as $child)
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs($child['route']) ? 'active' : '' }}"
                                   href="{{ route($child['route']) }}">
                                    {{-- A dot rather than a second icon: a child
                                         list of eight distinct icons is harder to
                                         scan than eight aligned labels. --}}
                                    <span class="nav-icon nav-icon-bullet"></span>
                                    {{ __($child['label']) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @else
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs($item['route']) ? 'active' : '' }}"
                       href="{{ route($item['route']) }}">
                        <span class="nav-icon">
                            <i class="cil-{{ $item['icon'] ?? 'circle' }}" aria-hidden="true"></i>
                        </span>
                        {{ __($item['label']) }}
                    </a>
                </li>
            @endif
        @endforeach
    </ul>

    <div class="sidebar-footer border-top d-none d-md-flex">
        <button class="sidebar-toggler" type="button" data-sidebar-toggle
                aria-label="{{ __('common.toggle_navigation') }}"></button>
    </div>
</div>
