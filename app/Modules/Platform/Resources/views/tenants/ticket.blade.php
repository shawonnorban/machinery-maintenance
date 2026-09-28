@extends('platform::layout')
@use('App\Modules\Platform\Models\SupportTicket')
@section('title', $ticket->subject)

@section('content')
    <div class="mb-2">
        @if ($ticket->company)
            <a href="{{ route('platform.tenants.show', [$ticket->company, 'tickets']) }}"
               class="small text-decoration-none">
                ← {{ $ticket->company->name }}
            </a>
        @else
            <a href="{{ route('platform.tickets') }}" class="small text-decoration-none">
                ← {{ __('platform.support_ticket') }}
            </a>
        @endif
    </div>

    <x-page-header :title="$ticket->subject" :subtitle="$ticket->company?->name" />

    <div class="platform-panels">
        <section class="panel panel-wide">
            <div class="panel-body">
                @include('platform::partials._ticket_thread')
            </div>

            @if ($ticket->status === 'CLOSED')
                <div class="panel-body panel-divided text-body-secondary small">
                    {{ __('platform.ticket_closed') }}
                </div>
            @else
                <div class="panel-body panel-divided">
                    @error('body')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                    <form method="POST" action="{{ route('platform.tickets.reply', $ticket) }}">
                        @csrf
                        <textarea name="body" rows="3" required maxlength="5000" class="form-control mb-2"
                                  placeholder="{{ __('platform.ticket_reply_placeholder') }}"></textarea>
                        <button class="btn btn-primary">{{ __('platform.ticket_send') }}</button>
                    </form>
                </div>
            @endif
        </section>

        <section class="panel">
            <header class="panel-head">
                <i class="cil-settings" aria-hidden="true"></i>
                <span>{{ __('platform.ticket_manage') }}</span>
            </header>

            <div class="panel-body">
                <form method="POST" action="{{ route('platform.tickets.status', $ticket) }}" class="mb-3">
                    @csrf
                    <label for="status" class="form-label">{{ __('platform.ticket_status') }}</label>
                    <div class="d-flex gap-2">
                        <select id="status" name="status" class="form-select form-select-sm">
                            @foreach (SupportTicket::STATUSES as $status)
                                <option value="{{ $status }}" @selected($ticket->status === $status)>
                                    {{ __('platform.ticket_status_'.strtolower($status)) }}
                                </option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-primary">{{ __('common.save') }}</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('platform.tickets.assign', $ticket) }}">
                    @csrf
                    <label for="assigned_to" class="form-label">{{ __('platform.ticket_assignee') }}</label>
                    <div class="d-flex gap-2">
                        <select id="assigned_to" name="assigned_to" class="form-select form-select-sm">
                            <option value="">{{ __('platform.ticket_unassigned') }}</option>
                            @foreach ($staff as $person)
                                <option value="{{ $person->id }}" @selected($ticket->assigned_to === $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-primary">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
