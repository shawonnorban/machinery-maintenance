@extends('layouts.app')
@section('title', __('account.your_account'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('account.your_account') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('account.your_account')" :subtitle="$user->email" />

    @if (session('recovery_codes'))
        {{-- The one and only time these are readable. Flashed, never stored
             anywhere they can be asked for again — they are hashed exactly
             like a password, because that is what they are worth. --}}
        <div class="alert alert-warning">
            <div class="fw-semibold">{{ __('account.recovery_codes_shown_once') }}</div>
            <div class="row row-cols-2 row-cols-md-4 g-2 my-2">
                @foreach (session('recovery_codes') as $code)
                    <div class="col"><code class="user-select-all">{{ $code }}</code></div>
                @endforeach
            </div>
            <div class="small">{{ __('account.recovery_codes_hint') }}</div>
        </div>
    @endif

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

        <div class="col-lg-6">
            {{-- Second factor -------------------------------------------- --}}
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-shield-alt" aria-hidden="true"></i>
                    <span>{{ __('account.two_factor') }}</span>
                    <span class="ms-auto">
                        <x-status-pill :status="$mfaOn ? 'ON' : 'OFF'" :tone="$mfaOn ? 'success' : 'secondary'">
                            {{ $mfaOn ? __('account.mfa_on') : __('account.mfa_off') }}
                        </x-status-pill>
                    </span>
                </div>

                <div class="card-body">
                    @if ($mfaOn)
                        <p class="mb-3">
                            {{ trans_choice('account.recovery_codes_left', $recoveryRemaining, ['count' => $recoveryRemaining]) }}
                        </p>

                        @error('code')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                        <form method="POST" action="{{ route('app.account.mfa.recovery-codes') }}"
                              class="row g-2 align-items-end mb-3">
                            @csrf
                            <div class="col-7">
                                <label for="regen_code" class="form-label mb-1">{{ __('account.mfa_code') }}</label>
                                <input id="regen_code" name="code" type="text" class="form-control form-control-sm"
                                       inputmode="numeric" autocomplete="one-time-code" required maxlength="32">
                            </div>
                            <div class="col-5">
                                <button class="btn btn-sm btn-outline-secondary w-100">
                                    {{ __('account.new_recovery_codes') }}
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('app.account.mfa.disable') }}"
                              class="row g-2 align-items-end">
                            @csrf
                            @method('DELETE')
                            <div class="col-7">
                                <label for="off_code" class="form-label mb-1">{{ __('account.mfa_code') }}</label>
                                <input id="off_code" name="code" type="text" class="form-control form-control-sm"
                                       inputmode="numeric" autocomplete="one-time-code" required maxlength="32">
                            </div>
                            <div class="col-5">
                                <button class="btn btn-sm btn-outline-danger w-100">
                                    {{ __('account.turn_off') }}
                                </button>
                            </div>
                            {{-- A code, never the password alone. Somebody who
                                 has taken over a session must not be able to
                                 remove the factor that would stop them. --}}
                            <div class="col-12 form-text">{{ __('account.mfa_off_needs_code') }}</div>
                        </form>
                    @elseif ($enrolling)
                        <p>{{ __('account.mfa_scan') }}</p>

                        <div class="d-flex flex-wrap gap-3 align-items-start mb-3">
                            <div>{!! $enrolmentQr !!}</div>
                            <div>
                                <div class="small text-body-secondary">{{ __('account.mfa_manual_entry') }}</div>
                                <code class="user-select-all">{{ $enrolmentSecret }}</code>
                            </div>
                        </div>

                        @error('code')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                        <form method="POST" action="{{ route('app.account.mfa.confirm') }}"
                              class="row g-2 align-items-end">
                            @csrf
                            <div class="col-7">
                                <label for="confirm_code" class="form-label mb-1">{{ __('account.mfa_code') }}</label>
                                <input id="confirm_code" name="code" type="text" class="form-control"
                                       inputmode="numeric" autocomplete="one-time-code" required
                                       maxlength="16" autofocus>
                            </div>
                            <div class="col-5">
                                <button class="btn btn-primary w-100">{{ __('account.mfa_confirm') }}</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('app.account.mfa.cancel') }}" class="mt-2">
                            @csrf
                            <button class="btn btn-sm btn-link px-0">{{ __('common.cancel') }}</button>
                        </form>
                    @else
                        <p>{{ __('account.mfa_why') }}</p>

                        <form method="POST" action="{{ route('app.account.mfa.begin') }}">
                            @csrf
                            <button class="btn btn-primary">{{ __('account.turn_on') }}</button>
                        </form>
                    @endif
                </div>
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
