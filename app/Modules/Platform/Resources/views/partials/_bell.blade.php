{{-- The platform's own bell.

     Same markup and same table as the application's, and a separate file
     because that one is composed by AppShellComposer, which reads a tenant
     context platform staff do not have. Sharing the component would mean
     guarding every line of a composer against a case it was not written for.

     What it carries is what one member of platform staff did that the others
     should know about without going to look: support access opened, a customer
     stopped, a customer erased. --}}
<li class="nav-item dropdown">
    <a class="nav-link notification-bell" href="#" data-coreui-toggle="dropdown"
       role="button" aria-expanded="false"
       aria-label="{{ __('notification.notifications') }}">
        <i class="cil-bell" aria-hidden="true"></i>

        <span class="notification-bell-badge" @if ($unreadNotifications < 1) hidden @endif>
            {{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}
        </span>
    </a>

    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
        <div class="dropdown-header d-flex align-items-center gap-2">
            <span class="fw-semibold">{{ __('notification.notifications') }}</span>

            @if ($unreadNotifications > 0)
                <span class="badge bg-danger ms-auto">{{ $unreadNotifications }}</span>
            @endif
        </div>

        @forelse ($recentNotifications as $notification)
            @php
                $tone = match ($notification->severity) {
                    'CRITICAL' => 'danger',
                    'WARNING' => 'warning',
                    default => 'info',
                };
            @endphp

            <a class="dropdown-item notification-dropdown-item {{ $notification->read_at === null ? 'unread' : '' }}"
               href="{{ $notification->action_url ?? route('platform.notifications') }}">
                <div class="d-flex align-items-start gap-2">
                    <span class="notification-dot bg-{{ $tone }}" aria-hidden="true"></span>

                    <span class="flex-grow-1">
                        <span class="d-block {{ $notification->read_at === null ? 'fw-semibold' : '' }}">
                            {{ Str::limit($notification->title, 48) }}
                        </span>

                        @if ($notification->body)
                            <span class="d-block text-body-secondary notification-dropdown-body">
                                {{ Str::limit($notification->body, 64) }}
                            </span>
                        @endif

                        <span class="d-block text-body-secondary notification-dropdown-time">
                            {{ $notification->created_at->diffForHumans() }}
                        </span>
                    </span>
                </div>
            </a>
        @empty
            <div class="dropdown-item-text text-body-secondary">
                {{ __('notification.no_notifications') }}
            </div>
        @endforelse

        <div class="dropdown-divider"></div>

        <a class="dropdown-item text-center fw-semibold" href="{{ route('platform.notifications') }}">
            {{ __('notification.view_all') }}
        </a>
    </div>
</li>
