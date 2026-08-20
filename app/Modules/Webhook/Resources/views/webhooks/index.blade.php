@extends('layouts.app')
@section('title', __('webhook.webhooks'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('webhook.webhooks') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('webhook.webhooks')" />

    @if ($newSecret)
        {{-- The one and only time this is readable. Flashed, never stored
             anywhere the customer can ask for it again. --}}
        <div class="alert alert-warning">
            <div class="fw-semibold">{{ __('webhook.secret_shown_once') }}</div>
            <code class="d-block my-2 user-select-all">{{ $newSecret }}</code>
            <div class="small">
                {{ __('webhook.secret_hint', ['header' => App\Modules\Webhook\Services\WebhookSigner::SIGNATURE_HEADER]) }}
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="cil-share-alt" aria-hidden="true"></i>
            <span>{{ __('webhook.endpoints') }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('webhook.url') }}</th>
                        <th>{{ __('webhook.events') }}</th>
                        <th class="text-end">{{ __('webhook.failures') }}</th>
                        <th>{{ __('webhook.status') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($endpoints as $endpoint)
                        <tr>
                            <td>
                                <a href="{{ route('app.webhooks.show', $endpoint) }}">
                                    {{ Str::limit($endpoint->url, 60) }}
                                </a>
                                <div class="small text-body-secondary">{{ $endpoint->description }}</div>
                            </td>
                            <td>{{ $endpoint->subscriptions_count }}</td>
                            <td class="text-end {{ $endpoint->consecutive_failure_count > 0 ? 'text-danger' : '' }}">
                                {{ $endpoint->consecutive_failure_count }}
                            </td>
                            <td>
                                <x-status-pill :status="$endpoint->status" :tone="match ($endpoint->status) {
                                    'ACTIVE' => 'success',
                                    'DISABLED' => 'danger',
                                    default => 'secondary',
                                }">
                                    {{ __('webhook.statuses.'.$endpoint->status) }}
                                </x-status-pill>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-empty-state :title="__('webhook.no_endpoints')"
                                               :description="__('webhook.no_endpoints_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <i class="cil-plus" aria-hidden="true"></i>
            <span>{{ __('webhook.new_endpoint') }}</span>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('app.webhooks.store') }}" class="row g-3">
                @csrf

                <div class="col-md-7">
                    <label for="url" class="form-label">{{ __('webhook.url') }}</label>
                    <input type="url" class="form-control @error('url') is-invalid @enderror" id="url"
                           name="url" value="{{ old('url') }}" placeholder="https://erp.example.com/hooks/machinery" required>
                    @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-5">
                    <label for="description" class="form-label">{{ __('webhook.description') }}</label>
                    <input type="text" class="form-control" id="description" name="description"
                           value="{{ old('description') }}">
                </div>

                <div class="col-12">
                    <span class="form-label d-block">{{ __('webhook.events') }}</span>

                    @error('events')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($events as $event)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="events[]"
                                       value="{{ $event }}" id="event-{{ $loop->index }}">
                                <label class="form-check-label" for="event-{{ $loop->index }}">
                                    {{ __(App\Modules\Webhook\Services\WebhookEvents::label($event)) }}
                                    <code class="small">{{ $event }}</code>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-12">
                    <button class="btn btn-info text-white">{{ __('webhook.new_endpoint') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
