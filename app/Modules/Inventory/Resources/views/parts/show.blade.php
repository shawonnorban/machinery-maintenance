@extends('layouts.app')
@section('title', $part->part_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.inventory.parts') }}">{{ __('inventory.spare_parts') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $part->part_number }}</li>
@endsection

@section('content')
    <x-page-header :title="$part->part_number" :subtitle="$part->name">
        <x-slot:actions>
            @if ($part->is_critical_spare)
                <x-status-pill status="CRITICAL" tone="danger">{{ __('inventory.is_critical_spare') }}</x-status-pill>
            @endif
            @if ($part->hazardous)
                <x-status-pill status="HAZARDOUS" tone="warning">{{ __('inventory.hazardous') }}</x-status-pill>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-list" aria-hidden="true"></i>
                    <span>{{ __('inventory.details') }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ __('inventory.category') }}</dt>
                        <dd class="col-sm-7">{{ $part->category?->label() ?? '—' }}</dd>

                        <dt class="col-sm-5">{{ __('inventory.brand') }}</dt>
                        <dd class="col-sm-7">{{ $part->brand ?? '—' }}</dd>

                        <dt class="col-sm-5">{{ __('inventory.unit') }}</dt>
                        <dd class="col-sm-7">{{ $part->unit }}</dd>

                        <dt class="col-sm-5">{{ __('inventory.minimum_stock') }}</dt>
                        <dd class="col-sm-7">{{ $part->minimum_stock }}</dd>

                        <dt class="col-sm-5">{{ __('inventory.reorder_level') }}</dt>
                        <dd class="col-sm-7">
                            {{ $part->reorder_level }}
                            <div class="text-body-secondary small">{{ __('inventory.reorder_hint') }}</div>
                        </dd>

                        @if ($part->lead_time_days !== null)
                            <dt class="col-sm-5">{{ __('inventory.lead_time_days') }}</dt>
                            <dd class="col-sm-7">{{ $part->lead_time_days }}</dd>
                        @endif

                        @can('inventory.stock.view')
                            <dt class="col-sm-5">{{ __('inventory.unit_cost') }}</dt>
                            <dd class="col-sm-7">
                                {{ $part->unit_cost ?? '—' }}
                                {{-- Reference only. An issue is always costed at
                                     the weighted average in the ledger. --}}
                                <div class="text-body-secondary small">{{ __('inventory.unit_cost_hint') }}</div>
                            </dd>
                        @endcan
                    </dl>

                    <div class="form-text mt-3">{{ __('inventory.threshold_note') }}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @can('inventory.stock.view')
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-storage" aria-hidden="true"></i>
                        <span>{{ __('inventory.stock') }}</span>
                        <span class="ms-auto">
                            {{ __('inventory.on_hand') }}: <strong>{{ $part->totalOnHand() }}</strong>
                            {{ $part->unit }}
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('inventory.location') }}</th>
                                    <th class="text-end">{{ __('inventory.on_hand') }}</th>
                                    <th class="text-end">{{ __('inventory.reserved') }}</th>
                                    <th class="text-end">{{ __('inventory.available') }}</th>
                                    <th class="text-end">{{ __('inventory.weighted_average_cost') }}</th>
                                    <th class="text-end">{{ __('inventory.total_value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($balances as $balance)
                                    <tr>
                                        <td>
                                            {{ $balance->bin?->fullPath() }}
                                            @if ($balance->bin?->is_in_transit)
                                                <x-status-pill status="TRANSIT" tone="info">
                                                    {{ __('inventory.in_transit') }}
                                                </x-status-pill>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $balance->quantity_on_hand }}</td>
                                        <td class="text-end">{{ $balance->quantity_reserved }}</td>
                                        <td class="text-end fw-semibold">{{ $balance->available() }}</td>
                                        <td class="text-end">{{ $balance->weighted_average_cost }}</td>
                                        <td class="text-end">{{ $balance->totalValue() }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-0">
                                            <x-empty-state :title="__('inventory.no_stock')"
                                                           :description="__('inventory.no_stock_hint')" />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer small text-body-secondary">{{ __('inventory.available_hint') }}</div>
                </div>

                @if ($verification->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header">
                            <i class="cil-check-circle" aria-hidden="true"></i>
                            <span>{{ __('inventory.verify_ledger') }}</span>
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach ($verification as $check)
                                {{-- Proof rather than assertion. If the ledger ever
                                     stops replaying to the balance, a movement was
                                     written outside it, and this is where that shows
                                     up rather than in a month-end reconciliation. --}}
                                <div class="list-group-item d-flex align-items-center gap-2">
                                    <span>{{ $check['bin']?->fullPath() }}</span>
                                    <span class="ms-auto small {{ $check['result']['matches'] ? 'text-success' : 'text-danger fw-semibold' }}">
                                        @if ($check['result']['matches'])
                                            {{ trans_choice('inventory.ledger_matches', $check['result']['transactions'], ['count' => $check['result']['transactions']]) }}
                                        @else
                                            {{ __('inventory.ledger_mismatch', [
                                                'replayed' => $check['result']['replayed'],
                                                'balance' => $check['result']['balance'],
                                            ]) }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @include('inventory::parts._receive')

                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-history" aria-hidden="true"></i>
                        <span>{{ __('inventory.ledger') }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('inventory.transaction_at') }}</th>
                                    <th>{{ __('inventory.transaction_type') }}</th>
                                    <th>{{ __('inventory.bin') }}</th>
                                    <th class="text-end">{{ __('inventory.quantity') }}</th>
                                    <th class="text-end">{{ __('inventory.balance_after') }}</th>
                                    <th class="text-end">{{ __('inventory.wac_after') }}</th>
                                    <th>{{ __('inventory.reference') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($transactions as $transaction)
                                    <tr class="{{ $transaction->reverses_transaction_id !== null ? 'table-warning' : '' }}">
                                        <td>@dt($transaction->transaction_at)</td>
                                        <td>{{ __('inventory.type_'.strtolower($transaction->transaction_type)) }}</td>
                                        <td class="text-body-secondary">{{ $transaction->bin?->code }}</td>
                                        <td class="text-end {{ $transaction->isInbound() ? 'text-success' : 'text-danger' }}">
                                            {{ $transaction->signedQuantity() }}
                                        </td>
                                        <td class="text-end fw-semibold">{{ $transaction->balance_after }}</td>
                                        <td class="text-end">{{ $transaction->wac_after }}</td>
                                        <td class="small">
                                            @if ($transaction->workOrder !== null)
                                                <a href="{{ route('app.work-orders.show', $transaction->work_order_id) }}">
                                                    {{ $transaction->workOrder->work_order_number }}
                                                </a>
                                            @endif
                                            @if ($transaction->notes)
                                                <div class="text-body-secondary">{{ $transaction->notes }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-body-secondary">
                                            {{ __('inventory.no_transactions') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer small text-body-secondary">{{ __('inventory.ledger_hint') }}</div>
                </div>
            @endcan
        </div>
    </div>
@endsection
