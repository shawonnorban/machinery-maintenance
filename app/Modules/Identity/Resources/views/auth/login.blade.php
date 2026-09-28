@extends('layouts.auth')
@section('title', __('common.sign_in'))

@section('content')
    <div class="login-card">
        <h1>{{ __('common.login') }}</h1>
        <p class="login-subtitle">{{ __('common.sign_in_to_your_account') }}</p>

        @if (session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif

        @error('email')
            {{-- One generic message for a wrong password and an unknown
                 address alike: distinguishing them tells an attacker which
                 addresses are registered (SRS 50.4). --}}
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="input-group mb-3">
                <span class="input-group-text" aria-hidden="true">
                    <i class="cil-user"></i>
                </span>
                <input name="email" type="email" autocomplete="username" required autofocus
                       class="form-control @error('email') is-invalid @enderror"
                       aria-label="{{ __('common.email') }}"
                       placeholder="{{ __('common.email') }}"
                       value="{{ old('email') }}">
            </div>

            <div class="input-group mb-4">
                <span class="input-group-text" aria-hidden="true">
                    <i class="cil-lock-locked"></i>
                </span>
                <input name="password" type="password" autocomplete="current-password" required
                       class="form-control @error('password') is-invalid @enderror"
                       aria-label="{{ __('common.password') }}"
                       placeholder="{{ __('common.password') }}">
            </div>

            <div class="d-flex align-items-center">
                <button type="submit" class="btn btn-info text-white px-4">
                    {{ __('common.login') }}
                </button>
                <a href="{{ route('password.request') }}" class="btn btn-link ms-auto text-decoration-none">
                    {{ __('common.forgot_password') }}
                </a>
            </div>
        </form>
    </div>
@endsection
