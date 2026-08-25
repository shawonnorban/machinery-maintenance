@extends('platform::layout')
@section('title', __('platform.support_ticket'))

@section('content')
    <x-page-header :title="__('platform.support_ticket')" :subtitle="__('platform.tickets_desk_intro')" />

    <section class="panel panel-danger mb-4">
        <header class="panel-head">
            <i class="cil-envelope-letter" aria-hidden="true"></i>
            <span>{{ __('platform.tickets_open') }}</span>
            @if ($open->isNotEmpty())
                <span class="badge bg-danger ms-auto">{{ $open->count() }}</span>
            @endif
        </header>

        <div class="panel-list">
            @forelse ($open as $ticket)
                <a href="{{ route('platform.tickets.show', $ticket) }}" class="panel-list-item panel-list-item-link">
                    <div class="min-w-0">
                        <div class="fw-semibold">{{ $ticket->subject }}</div>
                        <div class="tenant-code">
                            {{ $ticket->company?->name }} ·
                            {{ __('platform.ticket_opened_by', ['name' => $ticket->opener?->name]) }}
                            · {{ $ticket->last_message_at?->diffForHumans() }}
                        </div>
                    </div>

                    <span class="badge ms-auto {{ $ticket->status === 'OPEN' ? 'bg-danger' : 'bg-warning text-dark' }}">
                        {{ __('platform.ticket_status_'.strtolower($ticket->status)) }}
                    </span>
                </a>
            @empty
                <div class="panel-list-item text-body-secondary small">{{ __('platform.tickets_none_open') }}</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <header class="panel-head">
            <i class="cil-history" aria-hidden="true"></i>
            <span>{{ __('platform.tickets_closed') }}</span>
        </header>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('platform.tenant') }}</th>
                        <th>{{ __('platform.ticket_subject') }}</th>
                        <th>{{ __('platform.ticket_status') }}</th>
                        <th>{{ __('platform.ticket_last_activity_column') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($closed as $ticket)
                        <tr>
                            <td>
                                @if ($ticket->company)
                                    <a href="{{ route('platform.tenants.show', [$ticket->company, 'tickets']) }}">
                                        {{ $ticket->company->name }}
                                    </a>
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('platform.tickets.show', $ticket) }}">{{ $ticket->subject }}</a>
                            </td>
                            <td class="small">{{ __('platform.ticket_status_'.strtolower($ticket->status)) }}</td>
                            <td class="small text-nowrap">@dt($ticket->last_message_at)</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <x-empty-state :title="__('platform.tickets_no_closed')"
                                               :description="__('platform.tickets_no_closed_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
