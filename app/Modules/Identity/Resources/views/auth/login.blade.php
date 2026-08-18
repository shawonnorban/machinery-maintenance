@extends('layouts.auth')
@section('title', __('common.sign_in'))

@section('content')
    <div class="card p-4 shadow-sm">
        <div class="card-body">
            <h1 class="h4 mb-1">{{ config('app.name') }}</h1>
            <p class="text-body-secondary mb-4">{{ __('common.sign_in') }}</p>

            <x-layout.flash />

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('common.email') }}</label>
                    <input id="email" name="email" type="email" autocomplete="username"
                           class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('common.password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="current-password"
                           class="form-control @error('password') is-invalid @enderror" required>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                    <label class="form-check-label" for="remember">{{ __('common.remember_me') }}</label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    {{ __('common.sign_in') }}
                </button>
            </form>
        </div>
    </div>
@endsection
