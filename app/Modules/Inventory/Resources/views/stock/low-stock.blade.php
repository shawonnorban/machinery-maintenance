@extends('layouts.app')
@section('title', __('inventory.low_stock'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.inventory.stock') }}">{{ __('inventory.inventory') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('inventory.low_stock') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('inventory.low_stock')" :subtitle="__('inventory.reorder_hint')" />

    <div class="card">
        <div class="card-header">
            <i class="cil-warning" aria-hidden="true"></i>
            <span>{{ __('inventory.low_stock') }}</span>
            <span class="ms-auto text-body-secondary">{{ $parts->count() }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="col-index">{{ __('common.row_number') }}</th>
                        <th>{{ __('inventory.part_number') }}</th>
                        <th>{{ __('inventory.name') }}</th>
                        <th class="text-end">{{ __('inventory.on_hand') }}</th>
                        <th class="text-end">{{ __('inventory.reorder_level') }}</th>
                        <th class="text-end">{{ __('inventory.minimum_stock') }}</th>
                        <th>{{ __('inventory.lead_time_days') }}</th>
                        <th>{{ __('inventory.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($parts as $index => $part)
                        @php $onHand = number_format((float) ($part->on_hand ?? 0), 4, '.', ''); @endphp

                        <tr class="{{ $part->is_critical_spare ? 'table-danger' : '' }}">
                            <td class="col-index">{{ $index + 1 }}</td>
                            <td>
                                <a href="{{ route('app.inventory.parts.show', $part) }}" class="fw-semibold">
                                    {{ $part->part_number }}
                                </a>
                            </td>
                            <td>{{ $part->name }}</td>
                            <td class="text-end fw-semibold text-danger">{{ $onHand }} {{ $part->unit }}</td>
                            <td class="text-end text-body-secondary">{{ $part->reorder_level }}</td>
                            <td class="text-end text-body-secondary">{{ $part->minimum_stock }}</td>
                            <td>{{ $part->lead_time_days ?? '—' }}</td>
                            <td>
                                @if ($part->is_critical_spare)
                                    {{-- A part whose absence stops a critical
                                         machine is a different problem from one
                                         that is merely low, so it sorts first. --}}
                                    <x-status-pill status="CRITICAL" tone="danger">
                                        {{ __('inventory.is_critical_spare') }}
                                    </x-status-pill>
                                @elseif (bccomp($onHand, (string) $part->minimum_stock, 4) < 0)
                                    <x-status-pill status="BELOW_MIN" tone="warning">
                                        {{ __('inventory.below_minimum') }}
                                    </x-status-pill>
                                @else
                                    <x-status-pill status="BELOW_REORDER" tone="secondary">
                                        {{ __('inventory.below_reorder') }}
                                    </x-status-pill>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0">
                            <x-empty-state :title="__('inventory.no_parts')"
                                           :description="__('inventory.reorder_hint')" />
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
