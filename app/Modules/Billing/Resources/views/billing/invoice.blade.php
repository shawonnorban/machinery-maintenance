@extends('layouts.app')
@section('title', $invoice->invoice_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.billing') }}">{{ __('billing.billing') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $invoice->invoice_number }}</li>
@endsection

@section('content')
    <x-page-header :title="$invoice->invoice_number"
                   :subtitle="__('billing.issue_date').': '.$invoice->issue_date->toDateString()" />

    @if ($invoice->status === 'VOID')
        <div class="alert alert-secondary">{{ __('billing.void_reason', ['reason' => $invoice->void_reason]) }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-list" aria-hidden="true"></i>
                    <span>{{ __('billing.lines') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('billing.description') }}</th>
                                <th class="text-end">{{ __('billing.quantity') }}</th>
                                <th class="text-end">{{ __('billing.unit_price') }}</th>
                                <th class="text-end">{{ __('billing.line_amount') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($invoice->lines as $line)
                                <tr>
                                    <td>
                                        {{ $line->description }}
                                        @if ($line->period_start)
                                            <div class="small text-body-secondary">
                                                {{ $line->period_start->toDateString() }} — {{ $line->period_end?->toDateString() }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}</td>
                                    <td class="text-end">{{ number_format((float) $line->unit_price, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $line->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">{{ __('billing.subtotal') }}</th>
                                <td class="text-end">{{ number_format((float) $invoice->subtotal, 2) }}</td>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">{{ __('billing.tax') }}</th>
                                <td class="text-end">{{ number_format((float) $invoice->tax, 2) }}</td>
                            </tr>
                            <tr class="fw-semibold">
                                <th colspan="3" class="text-end">{{ __('billing.total') }}</th>
                                <td class="text-end">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency }}</td>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">{{ __('billing.paid_amount') }}</th>
                                <td class="text-end">{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                            </tr>
                            <tr class="fw-semibold">
                                <th colspan="3" class="text-end">{{ __('billing.balance_due') }}</th>
                                <td class="text-end {{ bccomp((string) $invoice->balance_due, '0', 4) > 0 ? 'text-danger' : '' }}">
                                    {{ number_format((float) $invoice->balance_due, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-check" aria-hidden="true"></i>
                    <span>{{ __('billing.payments') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @forelse ($invoice->payments as $payment)
                                <tr class="{{ $payment->status === 'REVERSED' ? 'text-body-secondary' : '' }}">
                                    <td>
                                        {{ $payment->payment_reference }}
                                        <div class="small text-body-secondary">
                                            {{ __('billing.methods.'.$payment->method) }}
                                            @if ($payment->recorder)
                                                · {{ $payment->recorder->name }}
                                            @endif
                                        </div>
                                    </td>
                                    <td>@dt($payment->paid_at)</td>
                                    <td class="text-end">
                                        {{ number_format((float) $payment->amount, 2) }}
                                        @if ($payment->status === 'REVERSED')
                                            <div class="small">{{ __('billing.invoice_statuses.VOID') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-body-secondary">{{ __('billing.no_payments') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($invoice->creditNotes->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="cil-note" aria-hidden="true"></i>
                        <span>{{ __('billing.credit_notes') }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                @foreach ($invoice->creditNotes as $note)
                                    <tr>
                                        <td>
                                            {{ $note->credit_note_number }}
                                            <div class="small text-body-secondary">{{ $note->reason }}</div>
                                        </td>
                                        <td class="text-end">{{ number_format((float) $note->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            @can('billing.payment.manage')
                @if ($invoice->isOpen())
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="cil-money" aria-hidden="true"></i>
                            <span>{{ __('billing.record_payment') }}</span>
                        </div>

                        <div class="card-body">
                            <form method="POST" action="{{ route('app.billing.invoice.pay', $invoice) }}" class="row g-3">
                                @csrf

                                <div class="col-12">
                                    <label for="amount" class="form-label">{{ __('billing.amount') }}</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror"
                                           id="amount" name="amount" value="{{ old('amount', $invoice->balance_due) }}" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12">
                                    <label for="method" class="form-label">{{ __('billing.method') }}</label>
                                    <select class="form-select" id="method" name="method" required>
                                        @foreach ($methods as $method)
                                            <option value="{{ $method }}" @selected(old('method') === $method)>
                                                {{ __('billing.methods.'.$method) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label for="payment_reference" class="form-label">{{ __('billing.payment_reference') }}</label>
                                    <input type="text" class="form-control" id="payment_reference"
                                           name="payment_reference" value="{{ old('payment_reference') }}">
                                </div>

                                <div class="col-12">
                                    <label for="paid_at" class="form-label">{{ __('billing.paid_at') }}</label>
                                    <input type="datetime-local" class="form-control" id="paid_at" name="paid_at"
                                           value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}">
                                </div>

                                <div class="col-12">
                                    <button class="btn btn-info text-white">{{ __('billing.record_payment') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan

            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">{{ __('billing.status') }}</dt>
                        <dd class="col-6">
                            <x-status-pill :status="$invoice->status" :tone="match ($invoice->status) {
                                'PAID' => 'success',
                                'OVERDUE' => 'danger',
                                'PARTIALLY_PAID' => 'warning',
                                default => 'info',
                            }">
                                {{ __('billing.invoice_statuses.'.$invoice->status) }}
                            </x-status-pill>
                        </dd>

                        <dt class="col-6">{{ __('billing.due_date') }}</dt>
                        <dd class="col-6">{{ $invoice->due_date->toDateString() }}</dd>

                        <dt class="col-6">{{ __('billing.contract_number') }}</dt>
                        <dd class="col-6">{{ $invoice->contract?->contract_number }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
