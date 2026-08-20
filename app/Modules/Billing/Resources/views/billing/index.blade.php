@extends('layouts.app')
@section('title', __('billing.billing'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('billing.billing') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('billing.billing')" :subtitle="$contract?->contract_number" />

    @if ($contract === null)
        {{-- No contract is not a lapsed contract: a company being onboarded, or
             a self-hosted deployment, is billed by nobody and restricted by
             nothing. --}}
        <x-empty-state :title="__('billing.no_contract')" :description="__('billing.no_contract_hint')" />
    @else
        @if ($contract->isReadOnly())
            <div class="alert alert-danger">
                {{ __('billing.read_only_banner') }}
                @if ($contract->read_only_at)
                    <div class="small">{{ __('billing.read_only_since', ['date' => $contract->read_only_at->toDateString()]) }}</div>
                @endif
            </div>
        @elseif (in_array($contract->status, ['PAST_DUE', 'GRACE'], true))
            <div class="alert alert-warning">{{ __('billing.past_due_banner') }}</div>
        @endif

        <div class="row">
            <div class="col-sm-6 col-xl-3">
                <x-kpi-tile :label="__('billing.status')"
                            :value="__('billing.statuses.'.$contract->status)"
                            :tone="match ($contract->status) {
                                'ACTIVE', 'TRIAL' => 'success',
                                'PAST_DUE', 'GRACE' => 'warning',
                                'READ_ONLY', 'ARCHIVED', 'CANCELLED' => 'danger',
                                default => 'secondary',
                            }" />
            </div>

            <div class="col-sm-6 col-xl-3">
                <x-kpi-tile :label="__('billing.outstanding')"
                            :value="number_format((float) $outstanding, 2).' '.$contract->currency"
                            :tone="bccomp($outstanding, '0', 4) > 0 ? 'danger' : 'success'" />
            </div>

            <div class="col-sm-6 col-xl-3">
                <x-kpi-tile :label="__('billing.amount')"
                            :value="number_format((float) $contract->amount, 2).' '.$contract->currency"
                            :caption="__('billing.cycles.'.$contract->billing_cycle)"
                            tone="primary" />
            </div>

            <div class="col-sm-6 col-xl-3">
                <x-kpi-tile :label="__('billing.grace_period')"
                            :value="__('billing.grace_days', ['days' => $contract->grace_period_days])"
                            tone="secondary" />
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="cil-description" aria-hidden="true"></i>
                        <span>{{ __('billing.subscription') }}</span>
                    </div>

                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-6">{{ __('billing.start_date') }}</dt>
                            <dd class="col-6">{{ $contract->start_date->toDateString() }}</dd>

                            <dt class="col-6">{{ __('billing.end_date') }}</dt>
                            <dd class="col-6">{{ $contract->end_date?->toDateString() ?? '—' }}</dd>

                            <dt class="col-6">{{ __('billing.trial_end') }}</dt>
                            <dd class="col-6">{{ $contract->trial_end?->toDateString() ?? '—' }}</dd>

                            <dt class="col-6">{{ __('billing.auto_renew') }}</dt>
                            <dd class="col-6">{{ $contract->auto_renew ? __('common.yes') : __('common.no') }}</dd>

                            <dt class="col-6">{{ __('billing.overage_policy') }}</dt>
                            <dd class="col-6">{{ __('billing.policies.'.$contract->overage_policy) }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <i class="cil-chart" aria-hidden="true"></i>
                        <span>{{ __('billing.usage') }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                @forelse ($usage as $metric => $row)
                                    <tr class="{{ $row->exceeded ? 'table-warning' : '' }}">
                                        <td>{{ __('billing.metrics.'.$metric) }}</td>
                                        <td class="text-end">{{ number_format((float) $row->value) }}</td>
                                        <td class="text-end small text-body-secondary">
                                            {{ $row->limit_value === null
                                                ? __('billing.no_limit')
                                                : __('billing.limit').': '.number_format((float) $row->limit_value) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="text-body-secondary">{{ __('billing.no_usage') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer small text-body-secondary">{{ __('billing.usage_hint') }}</div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="cil-money" aria-hidden="true"></i>
                        <span>{{ __('billing.invoices') }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('billing.invoice_number') }}</th>
                                    <th>{{ __('billing.due_date') }}</th>
                                    <th class="text-end">{{ __('billing.total') }}</th>
                                    <th class="text-end">{{ __('billing.balance_due') }}</th>
                                    <th>{{ __('billing.status') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($invoices as $invoice)
                                    <tr>
                                        <td>
                                            <a href="{{ route('app.billing.invoice', $invoice) }}">
                                                {{ $invoice->invoice_number }}
                                            </a>
                                        </td>
                                        <td>{{ $invoice->due_date->toDateString() }}</td>
                                        <td class="text-end">{{ number_format((float) $invoice->total, 2) }}</td>
                                        <td class="text-end {{ bccomp((string) $invoice->balance_due, '0', 4) > 0 ? 'text-danger' : '' }}">
                                            {{ number_format((float) $invoice->balance_due, 2) }}
                                        </td>
                                        <td>
                                            <x-status-pill :status="$invoice->status" :tone="match ($invoice->status) {
                                                'PAID' => 'success',
                                                'OVERDUE' => 'danger',
                                                'PARTIALLY_PAID' => 'warning',
                                                'VOID', 'WRITTEN_OFF' => 'secondary',
                                                default => 'info',
                                            }">
                                                {{ __('billing.invoice_statuses.'.$invoice->status) }}
                                            </x-status-pill>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-body-secondary">{{ __('billing.no_invoices') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
