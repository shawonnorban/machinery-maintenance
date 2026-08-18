@props(['menu'])

{{-- Rendered from a permission-filtered definition (Frontend 4.1). An item
     the user cannot use is not rendered at all. --}}
<div class="sidebar sidebar-dark sidebar-fixed" id="sidebar">
    <div class="sidebar-header border-bottom">
        <div class="sidebar-brand px-3 fw-semibold text-truncate">
            {{ config('app.name') }}
        </div>
    </div>

    <ul class="sidebar-nav" data-coreui="navigation">
        @foreach ($menu as $item)
            @if (isset($item['children']))
                <li class="nav-group">
                    <a class="nav-link nav-group-toggle" href="#">
                        <span class="nav-icon"></span> {{ __($item['label']) }}
                    </a>
                    <ul class="nav-group-items">
                        @foreach ($item['children'] as $child)
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs($child['route']) ? 'active' : '' }}"
                                   href="{{ route($child['route']) }}">
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
                        <span class="nav-icon"></span> {{ __($item['label']) }}
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
