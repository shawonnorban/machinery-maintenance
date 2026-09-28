{{-- The contract, read rather than filled in.

     The form that replaces it is folded away below, because it is used once a
     year and open it buried everything else on the page. --}}
<section class="panel panel-wide" id="contract">
    <header class="panel-head">
        <i class="cil-description" aria-hidden="true"></i>
        <span>{{ __('platform.contract') }}</span>
    </header>

    <div class="panel-body">
        @if ($contract)
            <div class="d-flex flex-wrap align-items-baseline gap-2 mb-3">
                <span class="panel-figure">{{ $contract->amount }} {{ $contract->currency }}</span>
                <span class="text-body-secondary">
                    / {{ __('platform.cycle_'.strtolower($contract->billing_cycle)) }}
                </span>
                <x-status-pill :status="$contract->status"
                               :tone="in_array($contract->status, ['ACTIVE', 'TRIAL'], true) ? 'success' : 'warning'">
                    {{ $contract->status }}
                </x-status-pill>
            </div>

            {{-- A field grid rather than a definition list down the page: four
                 short facts read faster side by side than stacked. --}}
            <div class="field-grid">
                <div>
                    <span class="field-label">{{ __('platform.contract_number') }}</span>
                    <span class="field-value">{{ $contract->contract_number }}</span>
                </div>
                <div>
                    <span class="field-label">{{ __('platform.term') }}</span>
                    <span class="field-value">
                        {{ $contract->start_date?->toDateString() }}
                        — {{ $contract->end_date?->toDateString() ?? __('platform.open_ended') }}
                    </span>
                </div>
                <div>
                    <span class="field-label">{{ __('platform.trial_end') }}</span>
                    <span class="field-value">{{ $contract->trial_end?->toDateString() ?? '—' }}</span>
                </div>
                <div>
                    <span class="field-label">{{ __('platform.grace_days') }}</span>
                    <span class="field-value">{{ $contract->grace_period_days }}</span>
                </div>
            </div>
        @else
            <div class="text-body-secondary">{{ __('platform.no_contract_yet') }}</div>
        @endif
    </div>

    <div class="panel-body panel-divided">
        <div class="panel-subhead">{{ __('platform.usage') }}</div>
        @include('platform::partials._usage')
    </div>

    @if ($contracts->count() > 1)
        <details class="panel-foot">
            <summary>
                {{ trans_choice('platform.earlier_contracts', $contracts->count() - 1, ['count' => $contracts->count() - 1]) }}
            </summary>

            <table class="table table-sm mb-0 mt-2">
                <tbody>
                    @foreach ($contracts->skip(1) as $old)
                        <tr>
                            <td class="small">{{ $old->contract_number }}</td>
                            <td class="small">
                                {{ $old->start_date?->toDateString() }}
                                — {{ $old->end_date?->toDateString() ?? '—' }}
                            </td>
                            <td class="small text-end">{{ $old->amount }} {{ $old->currency }}</td>
                            <td class="small"><span class="badge bg-secondary">{{ $old->status }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif

    <details class="panel-foot" @if ($errors->has('contract_number') || $errors->has('amount')) open @endif>
        <summary>{{ $contract ? __('platform.replace_contract') : __('platform.new_contract') }}</summary>

        <form method="POST" action="{{ route('platform.tenants.contract', $company) }}" class="mt-3">
            @csrf

            {{-- A form, not a plan picker. SRS 40: "No mandatory fixed
                 packages" — every term is set per customer, and a catalogue is
                 the one thing it refuses. --}}
            <div class="form-text mb-3">{{ __('platform.new_contract_hint') }}</div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="contract_number" class="form-label">{{ __('platform.contract_number') }}</label>
                    <input id="contract_number" name="contract_number" type="text" required maxlength="32"
                           value="{{ old('contract_number') }}"
                           class="form-control form-control-sm @error('contract_number') is-invalid @enderror">
                    @error('contract_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="start_date" class="form-label">{{ __('platform.start_date') }}</label>
                    <input id="start_date" name="start_date" type="date" required
                           value="{{ old('start_date', now()->toDateString()) }}"
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">{{ __('platform.end_date') }}</label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date') }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                    <label for="billing_cycle" class="form-label">{{ __('platform.cycle') }}</label>
                    <select id="billing_cycle" name="billing_cycle" class="form-select form-select-sm">
                        @foreach (['MONTHLY', 'QUARTERLY', 'YEARLY'] as $cycle)
                            <option value="{{ $cycle }}" @selected(old('billing_cycle') === $cycle)>
                                {{ __('platform.cycle_'.strtolower($cycle)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="amount" class="form-label">{{ __('platform.amount') }}</label>
                    <input id="amount" name="amount" type="number" step="0.01" min="0" required
                           value="{{ old('amount') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label for="currency" class="form-label">{{ __('platform.currency') }}</label>
                    <input id="currency" name="currency" type="text" required maxlength="3"
                           value="{{ old('currency', $company->base_currency) }}"
                           class="form-control form-control-sm">
                </div>

                <div class="col-md-4">
                    <label for="trial_end" class="form-label">{{ __('platform.trial_end') }}</label>
                    <input id="trial_end" name="trial_end" type="date" value="{{ old('trial_end') }}"
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label for="grace_period_days" class="form-label">{{ __('platform.grace_days') }}</label>
                    <input id="grace_period_days" name="grace_period_days" type="number" min="0" max="180"
                           value="{{ old('grace_period_days', 14) }}" required
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label for="overage_policy" class="form-label">{{ __('platform.overage') }}</label>
                    <select id="overage_policy" name="overage_policy" class="form-select form-select-sm">
                        @foreach (['WARN_ONLY', 'ALLOW_AND_BILL', 'BLOCK'] as $policy)
                            <option value="{{ $policy }}" @selected(old('overage_policy') === $policy)>
                                {{ __('platform.overage_'.strtolower($policy)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="included_factories" class="form-label">{{ __('platform.included_factories') }}</label>
                    <input id="included_factories" name="included_factories" type="number" min="0"
                           value="{{ old('included_factories') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label for="included_assets" class="form-label">{{ __('platform.included_assets') }}</label>
                    <input id="included_assets" name="included_assets" type="number" min="0"
                           value="{{ old('included_assets') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label for="included_users" class="form-label">{{ __('platform.included_users') }}</label>
                    <input id="included_users" name="included_users" type="number" min="0"
                           value="{{ old('included_users') }}" class="form-control form-control-sm">
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="auto_renew" value="1"
                               id="auto_renew" checked>
                        <label class="form-check-label" for="auto_renew">{{ __('platform.auto_renew') }}</label>
                    </div>
                </div>

                @if ($contract)
                    <div class="col-12">
                        <div class="alert alert-secondary py-2 mb-0 small">
                            {{ __('platform.supersedes_notice', ['number' => $contract->contract_number]) }}
                        </div>
                    </div>
                @endif

                <div class="col-12">
                    <button class="btn btn-primary">{{ __('platform.save_contract') }}</button>
                </div>
            </div>
        </form>
    </details>
</section>
