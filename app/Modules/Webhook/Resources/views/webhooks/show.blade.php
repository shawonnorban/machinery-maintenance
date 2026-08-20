@extends('layouts.app')
@section('title', __('webhook.webhook'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.webhooks.index') }}">{{ __('webhook.webhooks') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($endpoint->url, 40) }}</li>
@endsection

@section('content')
    <x-page-header :title="Str::limit($endpoint->url, 60)" :subtitle="$endpoint->description">
        <x-slot:actions>
            <a href="{{ route('app.webhooks.index') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('webhook.back_to_endpoints') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($endpoint->status === 'DISABLED')
        <div class="alert alert-danger d-flex flex-wrap align-items-center gap-3">
            <span>{{ $endpoint->disabled_reason }}</span>

            <form method="POST" action="{{ route('app.webhooks.enable', $endpoint) }}" class="ms-auto">
                @csrf
                <button class="btn btn-sm btn-outline-danger">{{ __('webhook.enable') }}</button>
            </form>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-settings" aria-hidden="true"></i>
                    <span>{{ __('webhook.webhook') }}</span>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('app.webhooks.update', $endpoint) }}" class="row g-3">
                        @csrf
                        @method('PUT')

                        <div class="col-12">
                            <label for="url" class="form-label">{{ __('webhook.url') }}</label>
                            <input type="url" class="form-control @error('url') is-invalid @enderror"
                                   id="url" name="url" value="{{ old('url', $endpoint->url) }}" required>
                            @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">{{ __('webhook.description') }}</label>
                            <input type="text" class="form-control" id="description" name="description"
                                   value="{{ old('description', $endpoint->description) }}">
                        </div>

                        <div class="col-12">
                            <span class="form-label d-block">{{ __('webhook.subscribed_events') }}</span>

                            @foreach ($events as $event)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="events[]"
                                           value="{{ $event }}" id="event-{{ $loop->index }}"
                                           @checked($endpoint->subscribesTo($event))>
                                    <label class="form-check-label" for="event-{{ $loop->index }}">
                                        {{ __(App\Modules\Webhook\Services\WebhookEvents::label($event)) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="col-12">
                            <button class="btn btn-info text-white">{{ __('webhook.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-lock-locked" aria-hidden="true"></i>
                    <span>{{ __('webhook.signing') }}</span>
                </div>

                <div class="card-body">
                    <dl class="row mb-3">
                        <dt class="col-6">{{ __('webhook.signing_algorithm') }}</dt>
                        <dd class="col-6">{{ $endpoint->signing_algorithm }}</dd>

                        <dt class="col-6">{{ __('webhook.secret_rotated_at') }}</dt>
                        <dd class="col-6">{{ $endpoint->secret_rotated_at?->toDateString() ?? '—' }}</dd>
                    </dl>

                    {{-- The secret itself is never rendered. It exists on the
                         row so requests can be signed, and nowhere else. --}}
                    <p class="small text-body-secondary">{{ __('webhook.rotation_hint') }}</p>

                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('app.webhooks.rotate', $endpoint) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning">{{ __('webhook.rotate_secret') }}</button>
                        </form>

                        @if ($endpoint->status === 'ACTIVE')
                            <form method="POST" action="{{ route('app.webhooks.pause', $endpoint) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary">{{ __('webhook.pause') }}</button>
                            </form>
                        @elseif ($endpoint->status === 'PAUSED')
                            <form method="POST" action="{{ route('app.webhooks.enable', $endpoint) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-success">{{ __('webhook.enable') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-list" aria-hidden="true"></i>
                    <span>{{ __('webhook.deliveries') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('webhook.event') }}</th>
                                <th>{{ __('webhook.sent_at') }}</th>
                                <th class="text-end">{{ __('webhook.attempts') }}</th>
                                <th>{{ __('webhook.response') }}</th>
                                <th>{{ __('webhook.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($deliveries as $delivery)
                                <tr>
                                    <td>
                                        <code class="small">{{ $delivery->event_type }}</code>
                                        <div class="small text-body-secondary">{{ $delivery->event_id }}</div>
                                    </td>
                                    <td>@dt($delivery->last_attempted_at ?? $delivery->created_at)</td>
                                    <td class="text-end">{{ $delivery->attempt_count }}</td>
                                    <td>
                                        {{ $delivery->response_status ?? '—' }}
                                        @if ($delivery->duration_ms !== null)
                                            <div class="small text-body-secondary">{{ $delivery->duration_ms }} ms</div>
                                        @endif
                                    </td>
                                    <td>
                                        <x-status-pill :status="$delivery->status" :tone="match ($delivery->status) {
                                            'DELIVERED' => 'success',
                                            'EXHAUSTED' => 'danger',
                                            'FAILED' => 'warning',
                                            default => 'secondary',
                                        }">
                                            {{ __('webhook.delivery_statuses.'.$delivery->status) }}
                                        </x-status-pill>

                                        @if ($delivery->next_retry_at)
                                            <div class="small text-body-secondary">
                                                {{ __('webhook.next_retry') }}: @dt($delivery->next_retry_at)
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($delivery->payload_json !== null)
                                            <form method="POST" action="{{ route('app.webhooks.redeliver', $delivery) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-primary">
                                                    {{ __('webhook.redeliver') }}
                                                </button>
                                            </form>
                                        @else
                                            {{-- Purged after 30 days; there is
                                                 nothing left to send again. --}}
                                            <span class="small text-body-secondary">{{ __('webhook.payload_purged') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-body-secondary">{{ __('webhook.no_deliveries') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
