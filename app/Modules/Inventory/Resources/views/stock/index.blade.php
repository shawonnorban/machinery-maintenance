@extends('layouts.app')
@section('title', __('inventory.stock'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('inventory.inventory') }}</li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('inventory.stock') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-4">
            <x-kpi-tile :label="__('inventory.total_value')"
                        :value="number_format((float) $totals['value'], 2)" tone="primary" />
        </div>
        <div class="col-sm-4">
            {{-- Stock that has left one factory and not yet reached the other.
                 Visible on purpose: a week-long road journey should not be a
                 hole in the valuation. --}}
            <x-kpi-tile :label="__('inventory.in_transit')"
                        :value="number_format((float) $totals['in_transit'], 2)" tone="info" />
        </div>
        <div class="col-sm-4">
            <x-kpi-tile :label="__('inventory.stock')" :value="number_format((int) $totals['lines'])" tone="secondary" />
        </div>
    </div>

    <form method="GET" action="{{ route('app.inventory.stock') }}" id="list-filter">
        <x-data-table :title="__('inventory.stock')" icon="cil-storage" :paginator="$balances">
            <x-slot:actions>
                <a href="{{ route('app.inventory.low-stock') }}" class="btn btn-sm btn-outline-warning">
                    {{ __('inventory.low_stock') }}
                </a>
            </x-slot:actions>

            <x-slot:toolbar>
                <select name="bin_id" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                    <option value="">{{ __('inventory.bin') }}: {{ __('common.all') }}</option>
                    @foreach ($bins as $bin)
                        <option value="{{ $bin->id }}" @selected(request('bin_id') === $bin->id)>
                            {{ $bin->fullPath() }}
                        </option>
                    @endforeach
                </select>
            </x-slot:toolbar>

            <thead>
                <tr>
                    <th class="col-index">{{ __('common.row_number') }}</th>
                    <th>{{ __('inventory.part_number') }}</th>
                    <th>{{ __('inventory.location') }}</th>
                    <th class="text-end">{{ __('inventory.on_hand') }}</th>
                    <th class="text-end">{{ __('inventory.reserved') }}</th>
                    <th class="text-end">{{ __('inventory.available') }}</th>
                    <th class="text-end">{{ __('inventory.weighted_average_cost') }}</th>
                    <th class="text-end">{{ __('inventory.total_value') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balances as $index => $balance)
                    @php
                        $part = $balance->sparePart;
                        $low = bccomp((string) $balance->quantity_on_hand, (string) ($part->reorder_level ?? '0'), 4) <= 0;
                    @endphp

                    <tr>
                        <td class="col-index">{{ $balances->firstItem() + $index }}</td>
                        <td>
                            <a href="{{ route('app.inventory.parts.show', $balance->spare_part_id) }}" class="fw-semibold">
                                {{ $part?->part_number }}
                            </a>
                            <div class="text-body-secondary">{{ Str::limit($part?->name ?? '', 32) }}</div>
                        </td>
                        <td class="text-body-secondary">{{ $balance->bin?->fullPath() }}</td>
                        <td class="text-end {{ $low ? 'text-danger fw-semibold' : '' }}">
                            {{ $balance->quantity_on_hand }}
                        </td>
                        <td class="text-end">{{ $balance->quantity_reserved }}</td>
                        <td class="text-end fw-semibold">{{ $balance->available() }}</td>
                        <td class="text-end">{{ $balance->weighted_average_cost }}</td>
                        <td class="text-end">{{ $balance->totalValue() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-0">
                        <x-empty-state :title="__('inventory.no_stock')" :description="__('inventory.no_stock_hint')" />
                    </td></tr>
                @endforelse
            </tbody>
        </x-data-table>
    </form>
@endsection
