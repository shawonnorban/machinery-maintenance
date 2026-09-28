{{-- Invoices, from the side that sends them (SRS 40).

     The machinery already existed and only the customer's own screen could see
     it: they could read an invoice and pay it, and nobody could raise one.
     `billing:advance` covers the normal month; this covers the first invoice,
     a mid-term adjustment, and the one raised early because somebody asked. --}}
<section class="panel panel-wide" id="invoices">
    <header class="panel-head">
        <i class="cil-money" aria-hidden="true"></i>
        <span>{{ __('platform.invoices') }}</span>

        @if ($invoices->isNotEmpty())
            @php($outstanding = $invoices->sum(fn ($i) => (float) $i->balance_due))
            @if ($outstanding > 0)
                <span class="ms-auto small text-danger fw-semibold">
                    {{ __('platform.outstanding', ['amount' => number_format($outstanding, 2)]) }}
                </span>
            @endif
        @endif
    </header>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('platform.invoice_number') }}</th>
                    <th>{{ __('platform.issued') }}</th>
                    <th class="text-end">{{ __('platform.total') }}</th>
                    <th class="text-end">{{ __('platform.balance') }}</th>
                    <th class="text-end">{{ __('common.actions') }}</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $invoice->invoice_number }}</div>
                            <x-status-pill :status="$invoice->status" :tone="match ($invoice->status) {
                                'PAID' => 'success',
                                'OVERDUE' => 'danger',
                                'VOID' => 'secondary',
                                'DRAFT' => 'warning',
                                default => 'info',
                            }">
                                {{ $invoice->status }}
                            </x-status-pill>
                        </td>

                        <td class="small">
                            {{ $invoice->issue_date?->toDateString() }}
                            <div class="text-body-secondary">
                                {{ __('platform.due') }} {{ $invoice->due_date?->toDateString() }}
                            </div>
                        </td>

                        <td class="text-end">{{ $invoice->total }} {{ $invoice->currency }}</td>
                        <td class="text-end {{ (float) $invoice->balance_due > 0 ? 'text-danger fw-semibold' : '' }}">
                            {{ $invoice->balance_due }}
                        </td>

                        <td class="text-end text-nowrap">
                            @if ($invoice->status === 'DRAFT')
                                {{-- Two steps on purpose. A draft can be
                                     corrected; an issued invoice is a document
                                     somebody has been sent, and its totals do
                                     not move afterwards. --}}
                                <form method="POST" action="{{ route('platform.invoices.issue', $invoice) }}"
                                      class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-primary">{{ __('platform.issue') }}</button>
                                </form>
                            @elseif ((float) $invoice->balance_due > 0 && $invoice->status !== 'VOID')
                                <button class="btn btn-sm btn-outline-success" type="button"
                                        data-coreui-toggle="collapse"
                                        data-coreui-target="#pay-{{ $invoice->id }}">
                                    {{ __('platform.record_payment') }}
                                </button>
                            @endif
                        </td>
                    </tr>

                    @if ((float) $invoice->balance_due > 0 && $invoice->status !== 'VOID' && $invoice->status !== 'DRAFT')
                        <tr class="collapse" id="pay-{{ $invoice->id }}">
                            <td colspan="5" class="bg-body-tertiary">
                                {{-- Most payments in this market arrive as a bank
                                     transfer somebody in the office reconciles,
                                     not as a card the customer enters. --}}
                                <form method="POST" action="{{ route('platform.invoices.pay', $invoice) }}"
                                      class="row g-2 align-items-end">
                                    @csrf
                                    <div class="col-md-3">
                                        <label class="form-label mb-1 small">{{ __('platform.amount') }}</label>
                                        <input name="amount" type="number" step="0.01" min="0.01" required
                                               value="{{ $invoice->balance_due }}"
                                               class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label mb-1 small">{{ __('platform.method') }}</label>
                                        <select name="method" class="form-select form-select-sm">
                                            @foreach (['BANK_TRANSFER', 'CASH', 'CHEQUE', 'MOBILE', 'CARD'] as $method)
                                                <option value="{{ $method }}">{{ __('platform.method_'.strtolower($method)) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label mb-1 small">{{ __('platform.reference') }}</label>
                                        <input name="reference" type="text" maxlength="255"
                                               class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-success w-100">{{ __('common.save') }}</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="p-0">
                            <x-empty-state :title="__('platform.no_invoices')"
                                           :description="__('platform.no_invoices_hint')" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <details class="panel-foot" @if ($errors->has('period_start')) open @endif>
        <summary>{{ __('platform.raise_invoice') }}</summary>

        <form method="POST" action="{{ route('platform.tenants.invoices.store', $company) }}" class="mt-3">
            @csrf

            @error('period_start')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="period_start" class="form-label mb-1 small">{{ __('platform.period_start') }}</label>
                    <input id="period_start" name="period_start" type="date" required
                           value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}"
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label for="period_end" class="form-label mb-1 small">{{ __('platform.period_end') }}</label>
                    <input id="period_end" name="period_end" type="date" required
                           value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}"
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label for="tax_rate" class="form-label mb-1 small">{{ __('platform.tax_rate') }}</label>
                    <input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100"
                           value="{{ old('tax_rate', 0) }}" class="form-control form-control-sm">
                </div>
            </div>

            <button class="btn btn-outline-primary mt-3">{{ __('platform.draft') }}</button>
        </form>
    </details>
</section>
