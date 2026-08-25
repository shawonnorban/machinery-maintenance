@extends('layouts.app')
@section('title', __('platform.support_ticket'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('platform.support_ticket') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('platform.support_ticket')" :subtitle="__('platform.tickets_intro')">
        <x-slot:actions>
            <a href="{{ route('app.support.tickets.create') }}" class="btn btn-primary">
                <i class="cil-plus" aria-hidden="true"></i> {{ __('platform.ticket_new') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($tickets as $ticket)
                <a href="{{ route('app.support.tickets.show', $ticket) }}"
                   class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center gap-2">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $ticket->subject }}</div>
                            <div class="small text-body-secondary">
                                {{ __('platform.ticket_last_activity', [
                                    'time' => $ticket->last_message_at?->diffForHumans(),
                                ]) }}
                            </div>
                        </div>

                        <span class="badge {{ match ($ticket->status) {
                            'OPEN' => 'bg-danger',
                            'IN_PROGRESS' => 'bg-warning text-dark',
                            'RESOLVED' => 'bg-success',
                            default => 'bg-secondary',
                        } }}">
                            {{ __('platform.ticket_status_'.strtolower($ticket->status)) }}
                        </span>
                    </div>
                </a>
            @empty
                <div class="list-group-item p-0">
                    <x-empty-state :title="__('platform.no_tickets')" :description="__('platform.no_tickets_hint_tenant')" />
                </div>
            @endforelse
        </div>
    </div>
@endsection
