@extends('platform::layout')
@section('title', __('platform.tenants'))

@php
    $active = collect($tenants)->filter(fn ($row) => $row['company']->status === 'ACTIVE')->count();
    $suspended = count($tenants) - $active;
    $unbilled = collect($tenants)->filter(fn ($row) => $row['contract'] === null)->count();
    $supportOpen = $openGrants->count();

    // The growth trend, entirely from monthlyGrowth: no figure on this card is
    // invented to fill the space. A single month of history compares to
    // nothing, so the trend line is silent rather than guessing.
    $growthValues = array_values($monthlyGrowth);
    $thisMonth = end($growthValues) ?: 0;
    $lastMonth = $growthValues[count($growthValues) - 2] ?? null;
    $growthDelta = $lastMonth === null ? null : $thisMonth - $lastMonth;
@endphp

@section('content')
    <x-page-header :title="__('platform.tenants')" :subtitle="__('platform.tenants_intro')">
        <x-slot:actions>
            <a href="{{ route('platform.tenants.create') }}" class="btn btn-primary">
                <i class="cil-plus" aria-hidden="true"></i> {{ __('platform.new_tenant') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- The four numbers somebody running this actually opens the page for.
         Two of them are problems rather than statistics: a customer nobody
         will invoice, and somebody inside a customer right now. --}}
    <div class="platform-figures">
        <div class="figure-card figure-card--primary">
            <div class="figure-value">
                {{ count($tenants) }}

                @if ($growthDelta !== null && $growthDelta !== 0)
                    {{-- The one trend worth showing: onboarding has a real
                         six-month history behind it. Every other card would
                         need a comparison that does not exist yet, so none of
                         them invent one. --}}
                    <span class="figure-trend {{ $growthDelta > 0 ? 'is-up' : 'is-down' }}">
                        <i class="cil-{{ $growthDelta > 0 ? 'arrow-top' : 'arrow-bottom' }}"
                           aria-hidden="true"></i>
                        {{ abs($growthDelta) }}
                    </span>
                @endif
            </div>
            <div class="figure-label">{{ __('platform.figure_tenants') }}</div>

            @include('platform::partials._growth_chart', ['months' => $monthlyGrowth])
        </div>

        <div class="figure-card figure-card--info">
            <div class="figure-value">{{ $active }}</div>
            <div class="figure-label">{{ __('platform.figure_active') }}</div>
        </div>

        <div class="figure-card figure-card--warning">
            <div class="figure-value">{{ $unbilled }}</div>
            <div class="figure-label">{{ __('platform.figure_unbilled') }}</div>
        </div>

        <div class="figure-card figure-card--danger">
            <div class="figure-value">{{ $supportOpen }}</div>
            <div class="figure-label">{{ __('platform.figure_support_open') }}</div>
        </div>

        @if ($suspended > 0)
            <div class="figure-card figure-card--dark">
                <div class="figure-value">{{ $suspended }}</div>
                <div class="figure-label">{{ __('platform.figure_suspended') }}</div>
            </div>
        @endif
    </div>

    {{-- A grid of cards rather than a table.

         This list is a few dozen customers looked at one at a time, not six
         hundred rows scanned — so each one gets room for the four things a
         decision needs (who, what they pay, how big they are, what is wrong)
         and its own buttons. A table put every action behind one narrow
         column at the end, which is where they were hard to find. --}}
    <div class="tenant-grid">
        @forelse ($tenants as $row)
            @php
                $company = $row['company'];
                $grants = $openGrants[$company->id] ?? collect();
                $isSuspended = $company->status !== 'ACTIVE';
            @endphp

            <article class="tenant-card
                {{ $isSuspended ? 'is-suspended' : '' }}
                {{ $grants->isNotEmpty() ? 'has-support' : '' }}
                {{ ! $isSuspended && $grants->isEmpty() && $row['contract'] === null ? 'is-unbilled' : '' }}">

                <header class="tenant-card-head">
                    @include('platform::partials._mark', ['company' => $company])

                    <div class="min-w-0">
                        <a href="{{ route('platform.tenants.show', $company) }}" class="tenant-card-name">
                            {{ $company->name }}
                        </a>
                        <div class="tenant-code">{{ $company->code }} · {{ $company->timezone }}</div>
                    </div>

                    @if ($isSuspended)
                        <span class="badge bg-danger ms-auto">
                            {{ __('platform.company_'.strtolower($company->status)) }}
                        </span>
                    @endif
                </header>

                @if ($grants->isNotEmpty())
                    {{-- On the card, not only inside the tenant: "who is inside
                         a customer right now" should be answerable at a glance,
                         not by opening ten pages. --}}
                    <div class="tenant-card-alert">
                        <i class="cil-lock-unlocked" aria-hidden="true"></i>
                        {{ __('platform.support_open_by', ['name' => $grants->first()->holder?->name]) }}
                    </div>
                @endif

                <div class="tenant-card-money">
                    @if ($row['contract'])
                        <span class="tenant-card-amount">
                            {{ $row['contract']->amount }} {{ $row['contract']->currency }}
                        </span>
                        <span class="text-body-secondary">
                            / {{ __('platform.cycle_'.strtolower($row['contract']->billing_cycle)) }}
                        </span>
                        <x-status-pill :status="$row['contract']->status"
                                       :tone="in_array($row['contract']->status, ['ACTIVE', 'TRIAL'], true) ? 'success' : 'warning'">
                            {{ $row['contract']->status }}
                        </x-status-pill>
                    @else
                        {{-- A customer with no contract is a customer nobody
                             will invoice. Worth seeing here rather than at the
                             end of a month. --}}
                        <span class="badge bg-warning text-dark">{{ __('platform.no_contract') }}</span>
                    @endif
                </div>

                <dl class="tenant-card-stats">
                    <div>
                        <dt>{{ __('platform.factories') }}</dt>
                        <dd>{{ $row['factories'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('platform.assets') }}</dt>
                        <dd>{{ $row['assets'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('platform.users') }}</dt>
                        <dd>{{ $row['users'] }}</dd>
                    </div>
                </dl>

                {{-- Tab URLs, not fragments. These were #contract, #support
                     and #close back when a customer was one long page; every
                     one of them became a link to nothing the moment the page
                     was split, and a fragment that matches no element fails
                     silently by scrolling nowhere. --}}
                <footer class="tenant-card-actions">
                    <a href="{{ route('platform.tenants.show', $company) }}" class="btn btn-sm btn-primary">
                        {{ __('platform.manage') }}
                    </a>

                    <a href="{{ route('platform.tenants.edit', $company) }}"
                       class="btn btn-sm btn-outline-secondary">
                        {{ __('common.edit') }}
                    </a>

                    <a href="{{ route('platform.tenants.show', [$company, 'billing']) }}"
                       class="btn btn-sm btn-outline-secondary">
                        {{ __('platform.bill_management') }}
                    </a>

                    <a href="{{ route('platform.tenants.show', [$company, 'support']) }}"
                       class="btn btn-sm btn-outline-danger ms-auto">
                        {{ __('platform.support_access') }}
                    </a>

                    {{-- A link, not a form. Closing an account asks for a typed
                         code and a reason, and neither belongs on a card in a
                         grid where the next card is one mis-click away. --}}
                    <a href="{{ route('platform.tenants.show', [$company, 'danger']) }}"
                       class="btn btn-sm btn-outline-danger" title="{{ __('platform.close_account') }}">
                        <i class="cil-trash" aria-hidden="true"></i>
                        <span class="visually-hidden">{{ __('platform.close_account') }}</span>
                    </a>
                </footer>
            </article>
        @empty
            <div class="tenant-grid-empty">
                <x-empty-state :title="__('platform.no_tenants')"
                               :description="__('platform.no_tenants_hint')" />
            </div>
        @endforelse
    </div>

    @if ($closed->isNotEmpty())
        {{-- Closed accounts, folded away. They are not customers any more, so
             they are off the grid above — but a list that simply forgot them
             would make a mistake unrecoverable in practice, which is the whole
             reason closing is reversible. --}}
        <details class="closed-tenants">
            <summary>
                {{ __('platform.closed_customers') }}
                <span class="badge bg-secondary">{{ $closed->count() }}</span>
            </summary>

            <div class="form-text mt-2 mb-3">{{ __('platform.closed_customers_hint') }}</div>

            @error('purge_code')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

            <div class="panel">
                <div class="panel-list">
                    @foreach ($closed as $company)
                        <div class="panel-list-item">
                            <div class="min-w-0">
                                <div class="fw-semibold">{{ $company->name }}</div>
                                <div class="tenant-code">
                                    {{ $company->code }} ·
                                    {{ __('platform.closed_on', ['date' => $company->deleted_at?->toDateString()]) }}
                                </div>
                            </div>

                            <div class="ms-auto d-flex gap-2">
                                <form method="POST" action="{{ route('platform.tenants.restore', $company->id) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">{{ __('platform.reopen') }}</button>
                                </form>

                                <button class="btn btn-sm btn-outline-danger" type="button"
                                        data-coreui-toggle="collapse"
                                        data-coreui-target="#erase-{{ $company->id }}">
                                    {{ __('platform.erase') }}
                                </button>
                            </div>
                        </div>

                        <div class="collapse" id="erase-{{ $company->id }}">
                            <div class="panel-body panel-divided">
                                <div class="form-text mb-2">{{ __('platform.erase_hint') }}</div>
                                {{-- The honest sentence. There is no tenant
                                     export yet (SRS §49), so this really is the
                                     one operation with nothing behind it. --}}
                                <div class="form-text mb-2">{{ __('platform.erase_no_export') }}</div>
                                <div class="form-text mb-3">{{ __('platform.erase_audit_kept') }}</div>

                                <form method="POST" action="{{ route('platform.tenants.purge', $company->id) }}"
                                      class="row g-2 align-items-end">
                                    @csrf
                                    @method('DELETE')

                                    <div class="col-md-5">
                                        <label class="form-label mb-1 small">{{ __('platform.reason') }}</label>
                                        <input name="reason" type="text" required minlength="10" maxlength="500"
                                               class="form-control form-control-sm">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label mb-1 small">
                                            {{ __('platform.confirm_code_label', ['code' => $company->code]) }}
                                        </label>
                                        <input name="confirm_code" type="text" required autocomplete="off"
                                               class="form-control form-control-sm"
                                               placeholder="{{ $company->code }}">
                                    </div>

                                    <div class="col-md-3">
                                        <button class="btn btn-sm btn-danger w-100">{{ __('platform.erase') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </details>
    @endif
@endsection
