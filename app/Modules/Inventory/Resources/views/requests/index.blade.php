@extends('layouts.app')
@section('title', __('inventory.part_requests'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('inventory.part_requests') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('inventory.part_requests')" :subtitle="__('inventory.part_requests_intro')" />

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('inventory.spare_part') }}</th>
                        <th class="text-end">{{ __('inventory.quantity_requested') }}</th>
                        <th class="text-end">{{ __('inventory.on_hand') }}</th>
                        <th>{{ __('work_order.work_order') }}</th>
                        <th>{{ __('asset.asset') }}</th>
                        <th>{{ __('work_order.priority') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($lines as $line)
                        @php($onHand = $line->sparePart?->totalOnHand() ?? '0')
                        @php($short = bccomp($onHand, (string) $line->quantity_requested, 4) < 0)

                        <tr class="{{ $short ? 'table-warning' : '' }}">
                            <td>
                                <a href="{{ route('app.inventory.parts.show', $line->spare_part_id) }}">
                                    {{ $line->sparePart?->part_number }}
                                </a>
                                <div class="small text-body-secondary">{{ $line->sparePart?->name }}</div>
                            </td>

                            <td class="text-end">
                                {{ $line->quantity_requested }} {{ $line->sparePart?->unit }}
                            </td>

                            <td class="text-end {{ $short ? 'fw-semibold text-danger' : '' }}">
                                {{ $onHand }}
                                @if ($short)
                                    {{-- The case the whole screen exists for: asked
                                         for, and not on the shelf. Until this was
                                         visible the job just sat there. --}}
                                    <div class="small">{{ __('inventory.not_enough_stock') }}</div>
                                @endif
                            </td>

                            <td>
                                <a href="{{ route('app.work-orders.show', $line->work_order_id) }}">
                                    {{ $line->workOrder?->work_order_number }}
                                </a>
                                <div class="small text-body-secondary">{{ $line->workOrder?->title }}</div>
                            </td>

                            <td class="small">
                                {{ $line->workOrder?->asset?->asset_code }}
                                <div class="text-body-secondary">{{ $line->workOrder?->asset?->name }}</div>
                            </td>

                            <td>
                                @include('work_order::work-orders._priority', [
                                    'priority' => $line->workOrder?->priority ?? 'LOW',
                                ])
                            </td>

                            <td class="text-end">
                                @if ($canIssue)
                                    {{-- Issuing needs a bin, so it happens on the
                                         work order where the store can see what is
                                         already on the job. --}}
                                    <a href="{{ route('app.work-orders.show', $line->work_order_id) }}#parts"
                                       class="btn btn-sm btn-info text-white">
                                        {{ __('inventory.go_and_issue') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-0">
                                <x-empty-state :title="__('inventory.no_part_requests')"
                                               :description="__('inventory.no_part_requests_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
