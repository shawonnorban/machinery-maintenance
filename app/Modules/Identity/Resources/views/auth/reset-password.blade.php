@extends('layouts.auth')
@section('title', __('common.reset_password'))

@section('content')
    <div class="login-card">
        <h1>{{ __('common.reset_password') }}</h1>
        <p class="login-subtitle">{{ __('common.choose_new_password') }}</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="input-group mb-3">
                <span class="input-group-text" aria-hidden="true"><i class="cil-envelope-closed"></i></span>
                <input name="email" type="email" required class="form-control"
                       aria-label="{{ __('common.email') }}"
                       placeholder="{{ __('common.email') }}" value="{{ old('email', $email) }}">
            </div>

            <div class="input-group mb-3">
                <span class="input-group-text" aria-hidden="true"><i class="cil-lock-locked"></i></span>
                <input name="password" type="password" required autocomplete="new-password"
                       class="form-control" aria-label="{{ __('common.password') }}"
                       placeholder="{{ __('common.new_password') }}">
            </div>

            <div class="input-group mb-2">
                <span class="input-group-text" aria-hidden="true"><i class="cil-lock-locked"></i></span>
                <input name="password_confirmation" type="password" required autocomplete="new-password"
                       class="form-control" aria-label="{{ __('common.confirm_password') }}"
                       placeholder="{{ __('common.confirm_password') }}">
            </div>

            {{-- SRS 50.1: minimum 10 characters, checked against a
                 known-breached list. Stated up front rather than discovered
                 through a validation error. --}}
            <p class="small text-body-secondary mb-4">{{ __('common.password_policy') }}</p>

            <button type="submit" class="btn btn-info text-white px-4">{{ __('common.reset_password') }}</button>
        </form>
    </div>
@endsection
