@extends('layouts.auth')
@section('title', __('tenancy.closed_title'))

@section('content')
    {{-- A closed account, which is not the same thing as a suspended one and
         should not read like it. A suspension can be lifted this afternoon;
         this account has been ended, and the person in front of it needs to
         know that before they wait for it to come back. --}}
    <div class="login-card" style="max-width: 30rem">
        <h1 class="h5 mb-1">{{ __('tenancy.closed_title') }}</h1>

        @if ($company)
            <p class="login-subtitle">{{ $company->name }}</p>
        @endif

        <div class="alert alert-secondary">
            {{ __('tenancy.closed_body') }}
        </div>

        @if ($since)
            <div class="mb-3 small text-body-secondary">
                {{ __('tenancy.closed_since', ['date' => $since->toDayDateTimeString()]) }}
            </div>
        @endif

        <p class="small">{{ __('tenancy.closed_what_now') }}</p>

        <div class="d-flex gap-2 mt-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-outline-secondary btn-sm">{{ __('common.sign_out') }}</button>
            </form>

            @if (auth()->check() && auth()->user()->accessibleCompanies()->count() > 1)
                {{-- Somebody who works for two companies in a group should not
                     lose both because one was closed. --}}
                <form method="POST" action="{{ route('app.switch-company') }}">
                    @csrf
                    <select name="company_id" class="form-select form-select-sm d-inline-block w-auto">
                        @foreach (auth()->user()->accessibleCompanies() as $other)
                            @continue($company && $other->id === $company->id)
                            <option value="{{ $other->id }}">{{ $other->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary">{{ __('tenancy.switch_company') }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection
