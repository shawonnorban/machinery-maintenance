@extends('layouts.app')
@section('title', $ticket->subject)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('app.support.tickets.index') }}">{{ __('platform.support_ticket') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">{{ $ticket->subject }}</li>
@endsection

@section('content')
    <x-page-header :title="$ticket->subject">
        <x-slot:actions>
            <span class="badge {{ match ($ticket->status) {
                'OPEN' => 'bg-danger',
                'IN_PROGRESS' => 'bg-warning text-dark',
                'RESOLVED' => 'bg-success',
                default => 'bg-secondary',
            } }}">
                {{ __('platform.ticket_status_'.strtolower($ticket->status)) }}
            </span>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-body">
            @include('platform::partials._ticket_thread')
        </div>

        @if ($ticket->status === 'CLOSED')
            <div class="card-footer text-body-secondary small">{{ __('platform.ticket_closed') }}</div>
        @else
            <div class="card-footer">
                @error('body')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                <form method="POST" action="{{ route('app.support.tickets.reply', $ticket) }}">
                    @csrf
                    <textarea name="body" rows="3" required maxlength="5000"
                              class="form-control mb-2" placeholder="{{ __('platform.ticket_reply_placeholder') }}"></textarea>
                    <button class="btn btn-primary">{{ __('platform.ticket_send') }}</button>
                </form>
            </div>
        @endif
    </div>
@endsection
