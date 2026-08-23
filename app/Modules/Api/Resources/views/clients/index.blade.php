@extends('layouts.app')
@section('title', __('api.api_clients'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('api.api_clients') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('api.api_clients')" :subtitle="__('api.api_clients_intro')" />

    @if (session('new_client_secret'))
        {{-- The one and only time this is readable. Flashed, never stored
             anywhere a support session could ask for it again. --}}
        <div class="alert alert-warning">
            <div class="fw-semibold">{{ __('api.secret_shown_once') }}</div>

            <dl class="row mb-0 mt-2">
                <dt class="col-sm-3">{{ __('api.client_id') }}</dt>
                <dd class="col-sm-9"><code class="user-select-all">{{ session('new_client_id') }}</code></dd>

                <dt class="col-sm-3">{{ __('api.client_secret') }}</dt>
                <dd class="col-sm-9"><code class="user-select-all">{{ session('new_client_secret') }}</code></dd>
            </dl>

            <div class="small mt-2">{{ __('api.secret_exchange_hint') }}</div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="cil-code" aria-hidden="true"></i>
            <span>{{ __('api.clients') }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('api.name') }}</th>
                        <th>{{ __('api.client_id') }}</th>
                        <th>{{ __('api.scopes') }}</th>
                        <th>{{ __('api.last_used') }}</th>
                        <th>{{ __('api.status') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($clients as $client)
                        <tr>
                            <td>
                                {{ $client->name }}
                                <div class="small text-body-secondary">
                                    {{ __('api.created_by', ['name' => $client->creator?->name ?? '—']) }}
                                </div>
                            </td>
                            <td><code class="small user-select-all">{{ $client->client_id }}</code></td>
                            <td class="small">
                                {{-- The scope list is the control, so it is shown
                                     rather than counted: "8 scopes" tells nobody
                                     whether this credential can close a work order. --}}
                                @foreach ($client->scopes() as $scope)
                                    <span class="badge bg-secondary-subtle text-body">{{ $scope }}</span>
                                @endforeach
                            </td>
                            <td class="small">
                                @if ($client->last_used_at)
                                    @dt($client->last_used_at)
                                @else
                                    <span class="text-body-secondary">{{ __('api.never_used') }}</span>
                                @endif
                            </td>
                            <td>
                                <x-status-pill :status="$client->status"
                                               :tone="$client->isUsable() ? 'success' : 'secondary'">
                                    {{ $client->isUsable() ? __('api.active') : __('api.inactive') }}
                                </x-status-pill>
                                @if ($client->active_tokens > 0)
                                    <div class="small text-body-secondary">
                                        {{ trans_choice('api.live_tokens', $client->active_tokens, ['count' => $client->active_tokens]) }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($client->isUsable())
                                    <form method="POST"
                                          action="{{ route('app.settings.api-clients.rotate', $client) }}"
                                          class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary btn-icon"
                                                title="{{ __('api.rotate_secret') }}"
                                                aria-label="{{ __('api.rotate_secret') }}">
                                            <i class="cil-loop-circular" aria-hidden="true"></i>
                                        </button>
                                    </form>

                                    <form method="POST"
                                          action="{{ route('app.settings.api-clients.revoke', $client) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('{{ __('api.revoke_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('api.revoke') }}"
                                                aria-label="{{ __('api.revoke') }}">
                                            <i class="cil-ban" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state :title="__('api.no_clients')"
                                               :description="__('api.no_clients_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('api.new_client') }}</div>

        <form method="POST" action="{{ route('app.settings.api-clients.store') }}">
            @csrf

            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">{{ __('api.name') }}</label>
                        <input id="name" name="name" type="text" class="form-control" required maxlength="255"
                               value="{{ old('name') }}" placeholder="{{ __('api.name_example') }}">
                        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label for="expires_at" class="form-label">{{ __('api.expires_at') }}</label>
                        <input id="expires_at" name="expires_at" type="date" class="form-control"
                               value="{{ old('expires_at') }}">
                        <div class="form-text">{{ __('api.expires_hint') }}</div>
                    </div>
                </div>

                <hr>

                <div class="fw-semibold">{{ __('api.scopes') }}</div>
                {{-- Nothing is ticked by default. An administrator who has not
                     decided yet means a credential that can do nothing, which
                     is the safe reading; "everything" is not. --}}
                <div class="form-text mb-3">{{ __('api.scopes_hint') }}</div>

                @error('scopes')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                <div class="row">
                    @foreach ($permissions as $module => $group)
                        <div class="col-md-4 mb-3">
                            <div class="fw-semibold text-uppercase small text-body-secondary">{{ $module }}</div>
                            @foreach ($group as $permission)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="scopes[]"
                                           value="{{ $permission->code }}"
                                           id="scope-{{ $permission->code }}">
                                    <label class="form-check-label small" for="scope-{{ $permission->code }}">
                                        {{ $permission->name }}
                                        <div class="text-body-secondary"><code>{{ $permission->code }}</code></div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card-footer">
                <button class="btn btn-primary">{{ __('api.create_client') }}</button>
            </div>
        </form>
    </div>
@endsection
