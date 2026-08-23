@extends('layouts.auth')
@section('title', __('account.mfa_challenge'))

@section('content')
    <div class="login-card">
        <h1>{{ __('account.mfa_challenge') }}</h1>
        <p class="login-subtitle">{{ __('account.mfa_challenge_for', ['email' => $email]) }}</p>

        @error('code')
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('mfa.challenge') }}">
            @csrf

            <div class="mb-3">
                <label for="code" class="form-label">{{ __('account.mfa_code') }}</label>
                {{-- inputmode numeric and one-time-code so a phone offers the
                     keypad and, on iOS, the code from the notification. Six
                     digits typed on a full keyboard on a factory floor is
                     where people give up. --}}
                <input id="code" name="code" type="text" class="form-control form-control-lg text-center"
                       inputmode="numeric" autocomplete="one-time-code" autofocus required
                       maxlength="32" placeholder="000000">
                <div class="form-text">{{ __('account.mfa_or_recovery') }}</div>
            </div>

            <button class="btn btn-primary w-100">{{ __('common.continue') }}</button>
        </form>

        <div class="mt-3 text-center small">
            <a href="{{ route('login') }}">{{ __('account.mfa_back_to_login') }}</a>
        </div>
    </div>
@endsection
