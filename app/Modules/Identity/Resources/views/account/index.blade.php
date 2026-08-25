@extends('layouts.app')
@section('title', __('account.your_account'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('account.your_account') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('account.your_account')" :subtitle="$user->email" />

    <div class="row">
        <div class="col-lg-6">
            {{-- Password ------------------------------------------------- --}}
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-lock-locked" aria-hidden="true"></i>
                    <span>{{ __('account.password') }}</span>
                </div>

                <form method="POST" action="{{ route('app.account.password') }}">
                    @csrf
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">
                                {{ __('account.current_password') }}
                            </label>
                            <input id="current_password" name="current_password" type="password"
                                   autocomplete="current-password" required
                                   class="form-control @error('current_password') is-invalid @enderror">
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('account.new_password') }}</label>
                            <input id="password" name="password" type="password"
                                   autocomplete="new-password" required
                                   class="form-control @error('password') is-invalid @enderror">
                            <div class="form-text">{{ __('account.password_policy') }}</div>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">
                                {{ __('account.confirm_password') }}
                            </label>
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                   autocomplete="new-password" required class="form-control">
                        </div>

                        {{-- Said before the button, not after the fact. --}}
                        <div class="alert alert-secondary py-2 mb-0 small">
                            {{ __('account.password_change_signs_out') }}
                        </div>
                    </div>

                    <div class="card-footer">
                        <button class="btn btn-primary">{{ __('account.change_password') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Devices ------------------------------------------------------------ --}}
    <div class="card mb-4">
        <div class="card-header">
            <i class="cil-devices" aria-hidden="true"></i>
            <span>{{ __('account.signed_in_devices') }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('account.device') }}</th>
                        <th>{{ __('account.ip_address') }}</th>
                        <th>{{ __('account.last_active') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr>
                            <td>
                                {{ $session['agent'] }}
                                @if ($session['is_current'])
                                    <span class="badge bg-info text-white">{{ __('account.this_device') }}</span>
                                @endif
                            </td>
                            <td class="small">{{ $session['ip_address'] }}</td>
                            <td class="small">@dt(\Carbon\CarbonImmutable::createFromTimestamp($session['last_activity']))</td>
                            <td class="text-end">
                                @unless ($session['is_current'])
                                    <form method="POST"
                                          action="{{ route('app.account.sessions.revoke', $session['id']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">
                                            {{ __('account.sign_out_device') }}
                                        </button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-0">
                                <x-empty-state :title="__('account.no_sessions')"
                                               :description="__('account.no_sessions_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($tokens->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <i class="cil-code" aria-hidden="true"></i>
                <span>{{ __('account.api_tokens') }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('account.token_name') }}</th>
                            <th>{{ __('account.last_used') }}</th>
                            <th>{{ __('account.expires') }}</th>
                            <th class="text-end">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tokens as $token)
                            <tr>
                                <td>
                                    {{ $token->name }}
                                    {{-- The last four characters, so somebody
                                         revoking one of six can tell which is
                                         which without being shown any in full. --}}
                                    <span class="text-body-secondary small">…{{ $token->last_four }}</span>
                                </td>
                                <td class="small">
                                    @if ($token->last_used_at) @dt($token->last_used_at) @else — @endif
                                </td>
                                <td class="small">@dt($token->expires_at)</td>
                                <td class="text-end">
                                    <form method="POST"
                                          action="{{ route('app.account.tokens.revoke', $token) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">{{ __('account.revoke') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
