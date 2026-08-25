@extends('platform::layout')
@section('title', $company->name)

@php
    $current = $contracts->first();
    $activeGrant = $grants->first(fn ($grant) => $grant->isActive());
@endphp

@section('content')
    <div class="mb-2">
        <a href="{{ route('platform.tenants') }}" class="small text-decoration-none">
            ← {{ __('platform.tenants') }}
        </a>
    </div>

    {{-- The same head as a card on the list, at page size: the mark, the name,
         the code. Somebody arriving here from the grid should recognise where
         they landed without reading. --}}
    <div class="tenant-head">
        {{-- The logo-or-monogram choice lives inside _mark now, so this reads
             the same as the card the reader clicked to get here. --}}
        @include('platform::partials._mark', ['company' => $company])

        <div class="min-w-0">
            <h1 class="h4 mb-0">{{ $company->name }}</h1>
            <div class="tenant-code">
                {{ $company->code }} · {{ $company->timezone }} · {{ $company->base_currency }}
            </div>
        </div>

        <div class="tenant-head-actions">
            <x-status-pill :status="$company->status"
                           :tone="$company->status === 'ACTIVE' ? 'success' : 'danger'">
                {{ __('platform.company_'.strtolower($company->status)) }}
            </x-status-pill>

            @if ($company->isSuspended())
                <form method="POST" action="{{ route('platform.tenants.suspend', $company) }}"
                      onsubmit="return confirm('{{ __('platform.reactivate_confirm') }}')">
                    @csrf
                    <button class="btn btn-sm btn-success">
                        <i class="cil-check-circle" aria-hidden="true"></i> {{ __('platform.reactivate') }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if (session('owner_password'))
        {{-- The one and only time this is readable. Not emailed from here: the
             address has not been verified, and a first password sent to a
             mistyped address is a credential handed to a stranger. --}}
        <div class="alert alert-warning">
            <div class="fw-semibold">{{ __('platform.owner_credentials_once') }}</div>
            <dl class="row mb-0 mt-2">
                <dt class="col-sm-2">{{ __('platform.owner_email') }}</dt>
                <dd class="col-sm-10"><code class="user-select-all">{{ session('owner_email') }}</code></dd>
                <dt class="col-sm-2">{{ __('platform.password') }}</dt>
                <dd class="col-sm-10"><code class="user-select-all">{{ session('owner_password') }}</code></dd>
            </dl>
        </div>
    @endif

    @if ($company->isSuspended())
        {{-- What the customer is being shown, shown back. Somebody reactivating
             a company should see the sentence their colleague wrote rather than
             guess at it. --}}
        <div class="alert alert-danger">
            <div class="fw-semibold">{{ __('platform.currently_suspended') }}</div>
            <div class="small mt-1">{{ $company->suspension_reason ?? __('tenancy.suspended_no_reason') }}</div>
            <div class="small text-body-secondary mt-1">
                {{ __('platform.suspended_by_on', [
                    'name' => $company->suspendedBy?->name ?? '—',
                    'date' => $company->suspended_at?->toDayDateTimeString() ?? '—',
                ]) }}
            </div>
        </div>
    @endif

    @if ($activeGrant)
        {{-- Loud, and above everything. Somebody is inside this customer's data
             right now, which is the most important fact on the page. --}}
        <div class="alert alert-danger d-flex flex-wrap align-items-center gap-3">
            <div>
                <div class="fw-semibold">
                    {{ __('platform.support_open_by', ['name' => $activeGrant->holder?->name]) }}
                </div>
                <div class="small">
                    {{ $activeGrant->reason }} · {{ __('platform.until') }} @dt($activeGrant->expires_at)
                </div>
            </div>

            @if ($activeGrant->granted_to === auth()->id())
                <form method="POST" action="{{ route('platform.support.close', $activeGrant) }}" class="ms-auto">
                    @csrf
                    <button class="btn btn-sm btn-light">{{ __('platform.hand_back') }}</button>
                </form>
            @endif
        </div>
    @endif

    {{-- Each name here is its own URL — a real page, not a JS tab — so the
         back button, a bookmark and an audit log's action_url all point at
         something that still means what it meant when it was written. --}}
    <nav class="tenant-tabs">
        @foreach ([
            'company' => 'platform.company_management',
            'billing' => 'platform.bill_management',
            'domains' => 'platform.domains',
            'support' => 'platform.support_access',
            'tickets' => 'platform.support_ticket',
            'analytics' => 'platform.analytics',
            'danger' => 'platform.danger_zone',
        ] as $key => $label)
            <a href="{{ $key === 'company' ? route('platform.tenants.show', $company) : route('platform.tenants.show', [$company, $key]) }}"
               class="{{ $tab === $key ? 'active' : '' }}">
                {{ __($label) }}
                @if ($key === 'tickets' && $openTicketCount > 0)
                    <span class="badge bg-danger">{{ $openTicketCount }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    @if ($tab === 'company')
        <div class="platform-panels">
            @include('platform::partials._company_view')
            @include('platform::partials._factories')
        </div>
    @elseif ($tab === 'billing')
        <div class="platform-panels">
            @include('platform::partials._contract', ['contract' => $current])
            @include('platform::partials._limits', ['contract' => $current])
            @include('platform::partials._invoices')
        </div>
    @elseif ($tab === 'domains')
        <div class="platform-panels">
            @include('platform::partials._domains')
        </div>
    @elseif ($tab === 'support')
        <div class="platform-panels">
            @include('platform::partials._support_access')
        </div>
    @elseif ($tab === 'tickets')
        <div class="platform-panels">
            @include('platform::partials._tickets_tab')
        </div>
    @elseif ($tab === 'analytics')
        <div class="platform-panels">
            @include('platform::partials._analytics')
        </div>
    @elseif ($tab === 'danger')
        <div class="platform-panels">
            @include('platform::partials._credentials')
            @include('platform::partials._danger')
        </div>
    @endif
@endsection
