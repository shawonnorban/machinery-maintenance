@extends('layouts.auth')
@section('title', __('tenancy.suspended_title'))

@section('content')
    {{-- A screen, not a bare 403. Somebody whose whole company has just stopped
         working will otherwise ring support to ask a question the platform
         already knows the answer to — and a refusal with no explanation reads
         as a fault in the product rather than a decision somebody made. --}}
    <div class="login-card" style="max-width: 30rem">
        <h1 class="h5 mb-1">{{ __('tenancy.suspended_title') }}</h1>
        <p class="login-subtitle">{{ $company->name }}</p>

        <div class="alert alert-danger">
            {{ __('tenancy.suspended_lede') }}
        </div>

        @if ($reason)
            <div class="mb-3">
                <div class="text-body-secondary small text-uppercase fw-semibold">
                    {{ __('tenancy.suspended_reason') }}
                </div>
                <div>{{ $reason }}</div>
            </div>
        @endif

        @if ($since)
            <div class="mb-3 small text-body-secondary">
                {{ __('tenancy.suspended_since', ['date' => $since->toDayDateTimeString()]) }}
            </div>
        @endif

        {{-- The two things worth saying: nothing has been deleted, and what to
             do next. A person reading this is afraid of the first and needs the
             second. --}}
        <p class="small">{{ __('tenancy.suspended_data_safe') }}</p>
        <p class="small">{{ __('tenancy.suspended_what_now') }}</p>

        <div class="d-flex gap-2 mt-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-outline-secondary btn-sm">{{ __('common.sign_out') }}</button>
            </form>

            @if (auth()->check() && auth()->user()->accessibleCompanies()->count() > 1)
                {{-- Somebody who works for two companies in a group should not
                     lose both because one was stopped. --}}
                <form method="POST" action="{{ route('app.switch-company') }}">
                    @csrf
                    <select name="company_id" class="form-select form-select-sm d-inline-block w-auto">
                        @foreach (auth()->user()->accessibleCompanies() as $other)
                            @continue($other->id === $company->id)
                            <option value="{{ $other->id }}">{{ $other->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary">{{ __('tenancy.switch_company') }}</button>
                </form>
            @endif
        </div>
    </div>
@endsection
