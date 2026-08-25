@extends('platform::layout')
@use('App\Modules\Billing\Models\PlatformExpense')
@section('title', __('platform.finance'))

@section('content')
    <x-page-header :title="__('platform.finance')" :subtitle="__('platform.finance_intro')">
        <x-slot:actions>
            {{-- The way in to recording a cost, at the top of the page rather
                 than folded into the bottom of a panel three screens down —
                 which is where it was, and where nobody found it. --}}
            <button type="button" class="btn btn-primary"
                    data-coreui-toggle="modal" data-coreui-target="#expense-modal">
                <i class="cil-plus" aria-hidden="true"></i> {{ __('platform.expense_add') }}
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- One set of figures per currency. Nothing is added across currencies:
         a customer billed in BDT and one billed in USD do not sum to anything,
         and a single number pretending they do is worse than two numbers. --}}
    @forelse ($totals as $currency => $figure)
        <div class="finance-currency">
            @if (count($totals) > 1)
                <div class="panel-subhead">{{ $currency }}</div>
            @endif

            <div class="platform-figures">
                <div class="figure-card figure-card--info">
                    <div class="figure-value">{{ number_format((float) $figure['invoiced'], 2) }}</div>
                    <div class="figure-label">{{ __('platform.total_invoiced') }} · {{ $currency }}</div>
                </div>

                <div class="figure-card figure-card--primary">
                    <div class="figure-value">{{ number_format((float) $figure['received'], 2) }}</div>
                    <div class="figure-label">{{ __('platform.total_received') }} · {{ $currency }}</div>
                </div>

                <div class="figure-card figure-card--warning">
                    <div class="figure-value">{{ number_format((float) $figure['due'], 2) }}</div>
                    <div class="figure-label">{{ __('platform.total_due') }} · {{ $currency }}</div>
                </div>

                <div class="figure-card figure-card--danger">
                    <div class="figure-value">{{ number_format((float) $figure['spent'], 2) }}</div>
                    <div class="figure-label">{{ __('platform.total_spent') }} · {{ $currency }}</div>
                </div>

                <div class="figure-card figure-card--dark">
                    <div class="figure-value">{{ number_format((float) $figure['net'], 2) }}</div>
                    <div class="figure-label">{{ __('platform.net') }} · {{ $currency }}</div>
                </div>
            </div>

            @if ((float) $figure['refunded'] > 0)
                {{-- Said out loud rather than folded silently into "received":
                     money given back has already been taken off the figure
                     above, and somebody reconciling against a bank statement
                     needs to know by how much. --}}
                <div class="form-text mb-4">
                    {{ __('platform.refunded_note', [
                        'amount' => number_format((float) $figure['refunded'], 2),
                        'currency' => $currency,
                    ]) }}
                </div>
            @endif
        </div>
    @empty
        <section class="panel mb-4">
            <x-empty-state :title="__('platform.no_money_yet')" :description="__('platform.no_money_yet_hint')" />
        </section>
    @endforelse

    <div class="platform-panels">
        <section class="panel panel-wide">
            <header class="panel-head">
                <i class="cil-chart" aria-hidden="true"></i>
                <span>{{ __('platform.money_by_month') }}</span>
            </header>

            <div class="panel-body">
                @php
                    $received = $monthly['received'];
                    $spent = $monthly['spent'];
                    $peak = max(1, max(array_merge(array_values($received), array_values($spent))));
                    $slot = 100 / count($received);
                @endphp

                @if (array_sum($received) === 0.0 && array_sum($spent) === 0.0)
                    <div class="text-body-secondary small">{{ __('platform.no_money_by_month') }}</div>
                @else
                    {{-- Two bars per month, side by side: what came in and what
                         went out. A single net bar hides a month that took a
                         lot and spent a lot, which is not the same business as
                         a quiet one. --}}
                    <svg class="analytics-chart finance-chart" viewBox="0 0 100 40"
                         preserveAspectRatio="none" role="img"
                         aria-label="{{ __('platform.money_by_month') }}">
                        @foreach ($received as $label => $value)
                            @php
                                $x = $loop->index * $slot;
                                $inHeight = $value === 0.0 ? 0.6 : max(1, ($value / $peak) * 34);
                                $outHeight = $spent[$label] === 0.0 ? 0.6 : max(1, ($spent[$label] / $peak) * 34);
                                $barWidth = $slot * 0.32;
                            @endphp

                            <rect x="{{ $x + $slot * 0.12 }}" y="{{ 36 - $inHeight }}"
                                  width="{{ $barWidth }}" height="{{ $inHeight }}" rx="0.4"
                                  class="finance-bar-in">
                                <title>{{ $label }} · {{ __('platform.total_received') }}: {{ number_format($value, 2) }}</title>
                            </rect>

                            <rect x="{{ $x + $slot * 0.5 }}" y="{{ 36 - $outHeight }}"
                                  width="{{ $barWidth }}" height="{{ $outHeight }}" rx="0.4"
                                  class="finance-bar-out">
                                <title>{{ $label }} · {{ __('platform.total_spent') }}: {{ number_format($spent[$label], 2) }}</title>
                            </rect>
                        @endforeach
                    </svg>

                    <div class="d-flex justify-content-between text-body-secondary" style="font-size: 0.6875rem">
                        <span>{{ array_key_first($received) }}</span>
                        <span>{{ array_key_last($received) }}</span>
                    </div>

                    <div class="finance-legend">
                        <span><i class="finance-swatch finance-swatch-in"></i> {{ __('platform.total_received') }}</span>
                        <span><i class="finance-swatch finance-swatch-out"></i> {{ __('platform.total_spent') }}</span>
                    </div>
                @endif
            </div>
        </section>

        {{-- What is late, as distinct from merely unpaid. An invoice inside its
             due date is business as usual; one past it is a phone call. --}}
        <section class="panel panel-wide {{ $overdue->isNotEmpty() ? 'panel-danger' : '' }}">
            <header class="panel-head">
                <i class="cil-warning" aria-hidden="true"></i>
                <span>{{ __('platform.overdue') }}</span>
                @if ($overdue->isNotEmpty())
                    <span class="badge bg-danger ms-auto">{{ $overdue->count() }}</span>
                @endif
            </header>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('platform.invoice_number') }}</th>
                            <th>{{ __('platform.tenant') }}</th>
                            <th>{{ __('platform.due') }}</th>
                            <th class="text-end">{{ __('platform.balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($overdue as $invoice)
                            @php($company = $companies[$invoice->company_id] ?? null)
                            <tr>
                                <td class="fw-semibold">{{ $invoice->invoice_number }}</td>
                                <td>
                                    @if ($company)
                                        <a href="{{ route('platform.tenants.show', [$company, 'billing']) }}">
                                            {{ $company->name }}
                                        </a>
                                    @else
                                        <span class="text-body-secondary">—</span>
                                    @endif
                                </td>
                                <td class="small text-nowrap text-danger">
                                    {{ $invoice->due_date?->toDateString() }}
                                    ({{ __('platform.days_late', [
                                        'days' => $invoice->due_date?->diffInDays(now()) ?? 0,
                                    ]) }})
                                </td>
                                <td class="text-end text-nowrap fw-semibold">
                                    {{ number_format((float) $invoice->balance_due, 2) }} {{ $invoice->currency }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-0">
                                    <x-empty-state :title="__('platform.nothing_overdue')"
                                                   :description="__('platform.nothing_overdue_hint')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel panel-wide">
            <header class="panel-head">
                <i class="cil-building" aria-hidden="true"></i>
                <span>{{ __('platform.per_customer') }}</span>
            </header>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('platform.tenant') }}</th>
                            <th class="text-end">{{ __('platform.total_invoiced') }}</th>
                            <th class="text-end">{{ __('platform.total_received') }}</th>
                            <th class="text-end">{{ __('platform.total_due') }}</th>
                            <th>{{ __('platform.last_payment') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('platform.tenants.show', [$row['company'], 'billing']) }}">
                                        {{ $row['company']->name }}
                                    </a>
                                    <div class="tenant-code">
                                        {{ $row['company']->code }}
                                        @if ($row['company']->trashed())
                                            {{-- Closed customers are listed here on purpose: one who
                                                 still owes money is exactly who this page is opened
                                                 to find. --}}
                                            · {{ __('platform.company_closed') }}
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    {{ number_format((float) $row['invoiced'], 2) }} {{ $row['currency'] }}
                                </td>
                                <td class="text-end text-nowrap">
                                    {{ number_format((float) $row['received'], 2) }}
                                </td>
                                <td class="text-end text-nowrap {{ (float) $row['due'] > 0 ? 'text-danger fw-semibold' : '' }}">
                                    {{ number_format((float) $row['due'], 2) }}
                                </td>
                                <td class="small text-nowrap">
                                    {{ $row['last_paid'] ? \Illuminate\Support\Carbon::parse($row['last_paid'])->toDateString() : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-0">
                                    <x-empty-state :title="__('platform.no_billed_customers')"
                                                   :description="__('platform.no_billed_customers_hint')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Every payment, one row each. The table above gives the totals; this
             is what somebody reconciles a bank statement against. --}}
        <section class="panel panel-wide">
            <header class="panel-head">
                <i class="cil-money" aria-hidden="true"></i>
                <span>{{ __('platform.payments_received') }}</span>
            </header>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('platform.expense_date') }}</th>
                            <th>{{ __('platform.tenant') }}</th>
                            <th>{{ __('platform.method') }}</th>
                            <th>{{ __('platform.reference') }}</th>
                            <th class="text-end">{{ __('platform.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            @php($company = $companies[$payment->company_id] ?? null)
                            <tr>
                                <td class="small text-nowrap">{{ $payment->paid_at?->toDateString() }}</td>
                                <td>
                                    @if ($company)
                                        <a href="{{ route('platform.tenants.show', [$company, 'billing']) }}">
                                            {{ $company->name }}
                                        </a>
                                    @else
                                        <span class="text-body-secondary">—</span>
                                    @endif
                                </td>
                                <td class="small">
                                    {{ __('platform.method_'.strtolower($payment->method)) }}
                                </td>
                                <td class="small">{{ $payment->payment_reference ?? '—' }}</td>
                                <td class="text-end text-nowrap fw-semibold">
                                    {{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-0">
                                    <x-empty-state :title="__('platform.no_payments')"
                                                   :description="__('platform.no_payments_hint')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel panel-wide">
            <header class="panel-head">
                <i class="cil-wallet" aria-hidden="true"></i>
                <span>{{ __('platform.spending') }}</span>

                <button type="button" class="btn btn-sm btn-outline-primary ms-auto"
                        data-coreui-toggle="modal" data-coreui-target="#expense-modal">
                    {{ __('platform.expense_add') }}
                </button>
            </header>

            @if ($byCategory !== [])
                <div class="panel-body">
                    <div class="field-grid">
                        @foreach ($byCategory as $row)
                            <div>
                                <span class="field-label">{{ __('platform.expense_category_'.strtolower($row['category'])) }}</span>
                                <span class="field-value">
                                    {{ number_format((float) $row['total'], 2) }} {{ $row['currency'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="table-responsive {{ $byCategory !== [] ? 'panel-divided' : '' }}">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('platform.expense_date') }}</th>
                            <th>{{ __('platform.expense_description') }}</th>
                            <th>{{ __('platform.expense_category') }}</th>
                            <th class="text-end">{{ __('platform.amount') }}</th>
                            <th class="text-end">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td class="small text-nowrap">{{ $expense->spent_on->toDateString() }}</td>
                                <td>
                                    {{ $expense->description }}
                                    @if ($expense->vendor)
                                        <div class="tenant-code">{{ $expense->vendor }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    {{ __('platform.expense_category_'.strtolower($expense->category)) }}
                                </td>
                                <td class="text-end text-nowrap">
                                    {{ number_format((float) $expense->amount, 2) }} {{ $expense->currency }}
                                </td>
                                <td class="text-end">
                                    <form method="POST"
                                          action="{{ route('platform.finance.expenses.destroy', $expense->id) }}"
                                          onsubmit="return confirm('{{ __('platform.expense_remove_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('common.delete') }}">
                                            <i class="cil-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-0">
                                    <x-empty-state :title="__('platform.no_expenses')"
                                                   :description="__('platform.no_expenses_hint')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- A modal rather than a fold at the bottom of a panel. Recording a cost
         is a short, self-contained errand that has nothing to do with whatever
         part of the page somebody was reading, and it kept the form invisible
         until three panels had been scrolled past. --}}
    <div class="modal fade" id="expense-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="POST" action="{{ route('platform.finance.expenses.store') }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title">{{ __('platform.expense_add') }}</h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal"
                            aria-label="{{ __('common.cancel') }}"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="spent_on" class="form-label">{{ __('platform.expense_date') }}</label>
                            <input id="spent_on" name="spent_on" type="date" required
                                   value="{{ old('spent_on', now()->toDateString()) }}"
                                   class="form-control @error('spent_on') is-invalid @enderror">
                            @error('spent_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="category" class="form-label">{{ __('platform.expense_category') }}</label>
                            <select id="category" name="category"
                                    class="form-select @error('category') is-invalid @enderror">
                                @foreach (PlatformExpense::CATEGORIES as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>
                                        {{ __('platform.expense_category_'.strtolower($category)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="expense_vendor" class="form-label">{{ __('platform.expense_vendor') }}</label>
                            <input id="expense_vendor" name="vendor" type="text" maxlength="255"
                                   value="{{ old('vendor') }}" class="form-control">
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">{{ __('platform.expense_description') }}</label>
                            <input id="description" name="description" type="text" required maxlength="255"
                                   value="{{ old('description') }}"
                                   class="form-control @error('description') is-invalid @enderror"
                                   placeholder="{{ __('platform.expense_description_example') }}">
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="amount" class="form-label">{{ __('platform.amount') }}</label>
                            <input id="amount" name="amount" type="number" step="0.01" min="0.01" required
                                   value="{{ old('amount') }}"
                                   class="form-control @error('amount') is-invalid @enderror">
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="expense_currency" class="form-label">{{ __('platform.currency') }}</label>
                            <input id="expense_currency" name="currency" type="text" required maxlength="3"
                                   value="{{ old('currency', 'BDT') }}"
                                   class="form-control @error('currency') is-invalid @enderror">
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="reference" class="form-label">{{ __('platform.expense_reference') }}</label>
                            <input id="reference" name="reference" type="text" maxlength="64"
                                   value="{{ old('reference') }}" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-coreui-dismiss="modal">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary">{{ __('platform.expense_add') }}</button>
                </div>
            </form>
        </div>
    </div>

@endsection
