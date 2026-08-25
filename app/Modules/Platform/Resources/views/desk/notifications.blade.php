@extends('platform::layout')
@section('title', __('notification.notifications'))

@section('content')
    <x-page-header :title="__('notification.notifications')"
                   :subtitle="__('platform.notifications_intro')">
        <x-slot:actions>
            @if ($notifications->contains(fn ($n) => $n->read_at === null))
                <form method="POST" action="{{ route('platform.notifications.read') }}">
                    @csrf
                    <button class="btn btn-outline-secondary">{{ __('notification.mark_all_read') }}</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="panel">
        <div class="panel-list">
            @forelse ($notifications as $notification)
                @php
                    $tone = match ($notification->severity) {
                        'CRITICAL' => 'danger',
                        'WARNING' => 'warning',
                        default => 'info',
                    };
                @endphp

                <div class="panel-list-item align-items-start">
                    <span class="notification-dot bg-{{ $tone }} mt-2" aria-hidden="true"></span>

                    <div class="min-w-0 ms-2">
                        <div class="{{ $notification->read_at === null ? 'fw-semibold' : '' }}">
                            {{ $notification->title }}
                        </div>

                        @if ($notification->body)
                            <div class="text-body-secondary small">{{ $notification->body }}</div>
                        @endif

                        <div class="tenant-code">@dt($notification->created_at)</div>
                    </div>

                    @if ($notification->action_url)
                        <a href="{{ $notification->action_url }}"
                           class="btn btn-sm btn-outline-secondary ms-auto">{{ __('common.view') }}</a>
                    @endif
                </div>
            @empty
                {{-- Not wrapped in a panel-list-item: that is a flex row, and
                     a centred block inside one shrinks to its longest word
                     instead of filling the card — which put the description
                     outside the card's left edge. --}}
                <x-empty-state :title="__('notification.no_notifications')"
                               :description="__('platform.notifications_empty_hint')" />
            @endforelse
        </div>
    </section>
@endsection
