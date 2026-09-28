@extends('layouts.app')
@section('title', __('notification.notifications'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('notification.notifications') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('notification.notifications')">
        <x-slot:actions>
            <a href="{{ route('app.notifications.preferences') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('notification.preferences') }}
            </a>

            @if ($unread > 0)
                <form method="POST" action="{{ route('app.notifications.read-all') }}">
                    @csrf
                    <button class="btn btn-sm btn-info text-white">{{ __('notification.mark_all_read') }}</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <ul class="nav nav-pills mb-3">
        @foreach (['UNREAD' => 'unread', 'ALL' => 'all'] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $filter === $key ? 'active' : '' }}"
                   href="{{ route('app.notifications', ['filter' => $key]) }}">
                    {{ __('notification.'.$label) }}
                    @if ($key === 'UNREAD' && $unread > 0)
                        <span class="badge bg-danger ms-1">{{ $unread }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-header">
            <i class="cil-bell" aria-hidden="true"></i>
            <span>{{ __('notification.notifications') }}</span>
        </div>

        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php
                    $tone = match ($notification->severity) {
                        'CRITICAL' => 'danger',
                        'WARNING' => 'warning',
                        default => 'info',
                    };
                @endphp

                <div class="list-group-item {{ $notification->isRead() ? '' : 'bg-body-tertiary' }}">
                    <div class="d-flex align-items-start gap-2">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <x-status-pill :status="$notification->severity" :tone="$tone">
                                    {{ __('notification.severity_'.strtolower($notification->severity)) }}
                                </x-status-pill>

                                <span class="{{ $notification->isRead() ? '' : 'fw-semibold' }}">
                                    {{ $notification->title }}
                                </span>

                                @if ($notification->isEscalation())
                                    {{-- Says why it arrived. An escalation that
                                         looks like an ordinary notification
                                         gives the reader no reason to treat it
                                         differently. --}}
                                    <x-status-pill status="ESCALATED" tone="danger">
                                        {{ __('notification.escalated') }}
                                    </x-status-pill>
                                @endif
                            </div>

                            @if ($notification->body)
                                <div class="text-body-secondary small mt-1">{{ $notification->body }}</div>
                            @endif

                            @if ($notification->isEscalation())
                                <div class="small text-danger mt-1">{{ __('notification.escalated_from') }}</div>
                            @endif

                            <div class="small text-body-secondary mt-1">@dt($notification->created_at)</div>
                        </div>

                        <div class="d-flex flex-column gap-1 align-items-end">
                            @if ($notification->action_url)
                                <a href="{{ $notification->action_url }}" class="btn btn-sm btn-info text-white">
                                    {{ __('notification.open') }}
                                </a>
                            @endif

                            @unless ($notification->isAcknowledged())
                                {{-- Acknowledging stops the escalation; marking
                                     read does not. Two buttons because they are
                                     two different statements. --}}
                                <form method="POST" action="{{ route('app.notifications.acknowledge', $notification) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">
                                        {{ __('notification.acknowledge') }}
                                    </button>
                                </form>
                            @endunless

                            @unless ($notification->isRead())
                                <form method="POST" action="{{ route('app.notifications.read', $notification) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">
                                        {{ __('notification.mark_read') }}
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </div>
                </div>
            @empty
                <div class="list-group-item p-0">
                    <x-empty-state :title="__('notification.no_notifications')"
                                   :description="__('notification.no_notifications_hint')" />
                </div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div class="table-footer">
                <div>
                    {{ __('common.showing_entries', [
                        'from' => $notifications->firstItem(),
                        'to' => $notifications->lastItem(),
                        'total' => number_format($notifications->total()),
                    ]) }}
                </div>
                <div class="ms-auto">{{ $notifications->onEachSide(1)->links() }}</div>
            </div>
        @endif

        <div class="card-footer small text-body-secondary">{{ __('notification.acknowledge_hint') }}</div>
    </div>
@endsection
