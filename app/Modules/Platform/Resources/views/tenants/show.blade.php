@extends('platform::layout')
@section('title', $company->name)

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1">{{ $company->name }}</h1>
            <div class="text-body-secondary small">{{ $company->code }} · {{ $company->timezone }}</div>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2">
            <x-status-pill :status="$company->status"
                           :tone="$company->status === 'ACTIVE' ? 'success' : 'danger'">
                {{ __('platform.company_'.strtolower($company->status)) }}
            </x-status-pill>

            <form method="POST" action="{{ route('platform.tenants.suspend', $company) }}"
                  onsubmit="return confirm('{{ $company->status === 'ACTIVE' ? __('platform.suspend_confirm') : __('platform.reactivate_confirm') }}')">
                @csrf
                <button class="btn btn-sm {{ $company->status === 'ACTIVE' ? 'btn-outline-danger' : 'btn-outline-success' }}">
                    {{ $company->status === 'ACTIVE' ? __('platform.suspend') : __('platform.reactivate') }}
                </button>
            </form>
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

    <div class="row g-4">
        {{-- Contract (SRS 40) ------------------------------------------- --}}
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('platform.contract') }}</div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('platform.contract_number') }}</th>
                                <th>{{ __('platform.term') }}</th>
                                <th class="text-end">{{ __('platform.amount') }}</th>
                                <th>{{ __('platform.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contracts as $contract)
                                <tr>
                                    <td>{{ $contract->contract_number }}</td>
                                    <td class="small">
                                        {{ $contract->start_date?->toDateString() }}
                                        — {{ $contract->end_date?->toDateString() ?? '—' }}
                                        <div class="text-body-secondary">
                                            {{ __('platform.cycle_'.strtolower($contract->billing_cycle)) }}
                                        </div>
                                    </td>
                                    <td class="text-end">{{ $contract->amount }} {{ $contract->currency }}</td>
                                    <td><span class="badge bg-secondary">{{ $contract->status }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-body-secondary p-3">
                                        {{ __('platform.no_contract_yet') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('platform.tenants.contract', $company) }}">
                    @csrf
                    <div class="card-body border-top">
                        {{-- A form, not a plan picker. SRS 40: "No mandatory
                             fixed packages" — every term is set per customer,
                             and a catalogue is the one thing it refuses. --}}
                        <div class="fw-semibold mb-1">{{ __('platform.new_contract') }}</div>
                        <div class="form-text mb-3">{{ __('platform.new_contract_hint') }}</div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="contract_number" class="form-label">{{ __('platform.contract_number') }}</label>
                                <input id="contract_number" name="contract_number" type="text" required maxlength="32"
                                       class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">{{ __('platform.start_date') }}</label>
                                <input id="start_date" name="start_date" type="date" required
                                       class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">{{ __('platform.end_date') }}</label>
                                <input id="end_date" name="end_date" type="date" class="form-control form-control-sm">
                            </div>

                            <div class="col-md-4">
                                <label for="billing_cycle" class="form-label">{{ __('platform.cycle') }}</label>
                                <select id="billing_cycle" name="billing_cycle" class="form-select form-select-sm">
                                    @foreach (['MONTHLY', 'QUARTERLY', 'YEARLY'] as $cycle)
                                        <option value="{{ $cycle }}">{{ __('platform.cycle_'.strtolower($cycle)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="amount" class="form-label">{{ __('platform.amount') }}</label>
                                <input id="amount" name="amount" type="number" step="0.01" min="0" required
                                       class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label for="currency" class="form-label">{{ __('platform.currency') }}</label>
                                <input id="currency" name="currency" type="text" required maxlength="3"
                                       value="{{ $company->base_currency }}" class="form-control form-control-sm">
                            </div>

                            <div class="col-md-4">
                                <label for="trial_end" class="form-label">{{ __('platform.trial_end') }}</label>
                                <input id="trial_end" name="trial_end" type="date" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label for="grace_period_days" class="form-label">{{ __('platform.grace_days') }}</label>
                                <input id="grace_period_days" name="grace_period_days" type="number" min="0" max="180"
                                       value="14" required class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label for="overage_policy" class="form-label">{{ __('platform.overage') }}</label>
                                <select id="overage_policy" name="overage_policy" class="form-select form-select-sm">
                                    @foreach (['WARN_ONLY', 'ALLOW_AND_BILL', 'BLOCK'] as $policy)
                                        <option value="{{ $policy }}">{{ __('platform.overage_'.strtolower($policy)) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="included_factories" class="form-label">{{ __('platform.included_factories') }}</label>
                                <input id="included_factories" name="included_factories" type="number" min="0"
                                       class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label for="included_assets" class="form-label">{{ __('platform.included_assets') }}</label>
                                <input id="included_assets" name="included_assets" type="number" min="0"
                                       class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label for="included_users" class="form-label">{{ __('platform.included_users') }}</label>
                                <input id="included_users" name="included_users" type="number" min="0"
                                       class="form-control form-control-sm">
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="auto_renew" value="1"
                                           id="auto_renew" checked>
                                    <label class="form-check-label" for="auto_renew">
                                        {{ __('platform.auto_renew') }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button class="btn btn-sm btn-primary">{{ __('platform.save_contract') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Support access (SRS 5.4) ------------------------------------ --}}
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">{{ __('platform.support_access') }}</div>

                <div class="card-body">
                    <div class="form-text mb-3">{{ __('platform.support_access_hint') }}</div>

                    @error('reason')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                    <form method="POST" action="{{ route('platform.support.open', $company) }}">
                        @csrf
                        <div class="mb-2">
                            <label for="reason" class="form-label">{{ __('platform.reason') }}</label>
                            <textarea id="reason" name="reason" rows="2" required maxlength="500"
                                      class="form-control form-control-sm"
                                      placeholder="{{ __('platform.reason_example') }}"></textarea>
                        </div>

                        <div class="row g-2 align-items-end">
                            <div class="col-6">
                                <label for="hours" class="form-label mb-1">{{ __('platform.hours') }}</label>
                                <input id="hours" name="hours" type="number" min="1" max="8" value="2" required
                                       class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <button class="btn btn-sm btn-outline-danger w-100">
                                    {{ __('platform.request_access') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="list-group list-group-flush">
                    @forelse ($grants as $grant)
                        <div class="list-group-item">
                            <div class="d-flex align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <div class="small fw-semibold">
                                        {{ $grant->holder?->name }}
                                        @if ($grant->isActive())
                                            <span class="badge bg-danger">{{ __('platform.active_now') }}</span>
                                        @endif
                                    </div>
                                    <div class="small text-body-secondary">{{ $grant->reason }}</div>
                                    <div class="small text-body-secondary">
                                        @dt($grant->starts_at) → @dt($grant->ended_at ?? $grant->expires_at)
                                    </div>
                                </div>
                            </div>

                            @if ($grant->isActive() && $grant->granted_to === auth()->id())
                                <form method="POST" action="{{ route('platform.support.enter', $grant) }}"
                                      class="row g-2 align-items-end mt-2">
                                    @csrf
                                    <div class="col-7">
                                        <label for="user-{{ $grant->id }}" class="form-label mb-1 small">
                                            {{ __('platform.act_as') }}
                                        </label>
                                        <select id="user-{{ $grant->id }}" name="user_id"
                                                class="form-select form-select-sm" required>
                                            @foreach ($members as $member)
                                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-5">
                                        <button class="btn btn-sm btn-danger w-100">{{ __('platform.enter') }}</button>
                                    </div>
                                </form>

                                <form method="POST" action="{{ route('platform.support.close', $grant) }}" class="mt-2">
                                    @csrf
                                    <button class="btn btn-sm btn-link px-0">{{ __('platform.hand_back') }}</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-body-secondary small">
                            {{ __('platform.no_grants') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('platform.factories') }}</div>
                <div class="list-group list-group-flush">
                    @foreach ($factories as $factory)
                        <div class="list-group-item d-flex">
                            <span>{{ $factory->name }}</span>
                            <span class="ms-auto text-body-secondary small">{{ $factory->code }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
