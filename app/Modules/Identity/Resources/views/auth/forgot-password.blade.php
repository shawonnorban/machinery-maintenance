@extends('layouts.auth')
@section('title', __('common.forgot_password'))

@section('content')
    <div class="login-card">
        <h1>{{ __('common.reset_password') }}</h1>
        <p class="login-subtitle">{{ __('common.reset_password_hint') }}</p>

        @if (session('status'))
            <div class="alert alert-success py-2">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="input-group mb-4">
                <span class="input-group-text" aria-hidden="true"><i class="cil-envelope-closed"></i></span>
                <input name="email" type="email" required autofocus
                       class="form-control @error('email') is-invalid @enderror"
                       aria-label="{{ __('common.email') }}"
                       placeholder="{{ __('common.email') }}" value="{{ old('email') }}">
            </div>

            <div class="d-flex align-items-center">
                <button type="submit" class="btn btn-info text-white px-4">{{ __('common.send_link') }}</button>
                <a href="{{ route('login') }}" class="btn btn-link ms-auto text-decoration-none">
                    {{ __('common.back_to_login') }}
                </a>
            </div>
        </form>
    </div>
@endsection
