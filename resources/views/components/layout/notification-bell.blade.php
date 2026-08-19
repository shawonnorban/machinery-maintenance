@props(['unreadNotifications' => 0, 'recentNotifications' => null])

@php $recent = $recentNotifications ?? collect(); @endphp

{{--
    The bell opens a dropdown rather than navigating.

    A bell that jumps straight to a full page makes checking "is anything
    waiting for me" cost a page load and lose whatever the person was doing.
    The dropdown answers that in place; the page is still there for reading
    through the history (Frontend 4.2).

    A count, not the rows, decides the badge — the header renders on every
    screen, and the recent list is only fetched when there is something to show.
--}}
<li class="nav-item dropdown">
    <a class="nav-link notification-bell" href="#" data-coreui-toggle="dropdown"
       role="button" aria-expanded="false"
       aria-label="{{ __('notification.notifications') }}">
        <i class="cil-bell" aria-hidden="true"></i>

        {{-- Always rendered, hidden when empty: a badge that only exists once
             there is something to count cannot be incremented by a socket
             message without rebuilding the header. --}}
        <span class="notification-bell-badge" data-notification-count
              @if ($unreadNotifications < 1) hidden @endif>
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

        @forelse ($recent as $notification)
            @php
                $tone = match ($notification->severity) {
                    'CRITICAL' => 'danger',
                    'WARNING' => 'warning',
                    default => 'info',
                };
            @endphp

            <a class="dropdown-item notification-dropdown-item {{ $notification->read_at === null ? 'unread' : '' }}"
               href="{{ $notification->action_url ?? route('app.notifications') }}">
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

        <a class="dropdown-item text-center fw-semibold" href="{{ route('app.notifications') }}">
            {{ __('notification.view_all') }}
        </a>
    </div>
</li>
