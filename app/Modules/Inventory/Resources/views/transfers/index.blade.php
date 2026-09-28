@extends('layouts.app')
@section('title', __('inventory.transfers'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('inventory.transfers') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('inventory.transfers')" :subtitle="__('inventory.transfers_intro')" />

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <label for="status" class="form-label mb-1">{{ __('inventory.status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">{{ __('inventory.all_statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>
                                {{ __('inventory.transfer_status_'.strtolower($status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-3 d-flex gap-2">
                    <button class="btn btn-outline-secondary">{{ __('common.search') }}</button>
                    <a href="{{ route('app.inventory.transfers') }}" class="btn btn-outline-secondary">
                        {{ __('common.clear') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    @forelse ($transfers as $transfer)
        @php($isSender = $factories->contains('id', $transfer->from_factory_id))
        @php($isReceiver = $factories->contains('id', $transfer->to_factory_id))

        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <span class="fw-semibold">{{ $transfer->transfer_number }}</span>
                    <span class="text-body-secondary ms-2">
                        {{ $transfer->fromFactory?->name }} → {{ $transfer->toFactory?->name }}
                    </span>
                </span>

                <x-status-pill :status="$transfer->status" :tone="match ($transfer->status) {
                    'RECEIVED' => 'success',
                    'REJECTED', 'CANCELLED' => 'danger',
                    'IN_TRANSIT' => 'warning',
                    default => 'secondary',
                }">
                    {{ __('inventory.transfer_status_'.strtolower($transfer->status)) }}
                </x-status-pill>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('inventory.spare_part') }}</th>
                            <th class="text-end">{{ __('inventory.quantity_requested') }}</th>
                            <th class="text-end">{{ __('inventory.dispatched') }}</th>
                            <th class="text-end">{{ __('inventory.received') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transfer->items as $item)
                            <tr>
                                <td>
                                    {{ $item->sparePart?->part_number }}
                                    <span class="text-body-secondary small">{{ $item->sparePart?->name }}</span>
                                </td>
                                <td class="text-end">{{ $item->quantity_requested }}</td>
                                <td class="text-end">{{ $item->quantity_dispatched ?? '—' }}</td>
                                <td class="text-end">{{ $item->quantity_received ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($transfer->status === 'IN_TRANSIT')
                {{-- The stock is in neither factory right now: it sits in an
                     in-transit bin, so a valuation taken while the van is on the
                     road still balances. --}}
                <div class="card-body py-2 small text-body-secondary border-top">
                    {{ __('inventory.in_transit_hint') }}
                </div>
            @endif

            <div class="card-body border-top d-flex flex-wrap gap-2">
                @if ($transfer->status === 'REQUESTED' && $isSender)
                    @can('inventory.transfer.approve')
                        <form method="POST" action="{{ route('app.inventory.transfers.approve', $transfer) }}">
                            @csrf
                            <button class="btn btn-sm btn-info text-white">{{ __('inventory.approve') }}</button>
                        </form>

                        <form method="POST" action="{{ route('app.inventory.transfers.reject', $transfer) }}"
                              class="d-flex gap-2">
                            @csrf
                            <input name="reason" type="text" class="form-control form-control-sm"
                                   placeholder="{{ __('inventory.reject_reason') }}" required maxlength="500">
                            <button class="btn btn-sm btn-outline-danger">{{ __('inventory.reject') }}</button>
                        </form>
                    @endcan
                @endif

                @if ($transfer->status === 'APPROVED' && $isSender)
                    @can('inventory.transfer.dispatch')
                        <form method="POST" action="{{ route('app.inventory.transfers.dispatch', $transfer) }}">
                            @csrf
                            <button class="btn btn-sm btn-info text-white">{{ __('inventory.dispatch') }}</button>
                        </form>
                    @endcan
                @endif

                @if ($transfer->status === 'IN_TRANSIT' && $isReceiver)
                    @can('inventory.transfer.receive')
                        <form method="POST" action="{{ route('app.inventory.transfers.receive', $transfer) }}">
                            @csrf
                            <button class="btn btn-sm btn-success">{{ __('inventory.confirm_receipt') }}</button>
                        </form>
                    @endcan
                @endif

                @if ($transfer->status === 'IN_TRANSIT' && ! $isReceiver)
                    {{-- Only the far end may confirm: a sending storekeeper doing
                         it marks stock as arrived while it is still on the van. --}}
                    <span class="small text-body-secondary">{{ __('inventory.awaiting_receipt') }}</span>
                @endif

                @if ($transfer->notes)
                    <span class="small text-body-secondary ms-auto">{{ $transfer->notes }}</span>
                @endif
            </div>
        </div>
    @empty
        <div class="card mb-4">
            <x-empty-state :title="__('inventory.no_transfers')" :description="__('inventory.no_transfers_hint')" />
        </div>
    @endforelse

    {{ $transfers->links() }}

    @can('inventory.transfer.create')
        <div class="card mt-4">
            <div class="card-header">{{ __('inventory.new_transfer') }}</div>

            <div class="card-body">
                <form method="POST" action="{{ route('app.inventory.transfers.store') }}">
                    @csrf

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label for="from_factory_id" class="form-label mb-1">{{ __('inventory.from_factory') }}</label>
                            <select id="from_factory_id" name="from_factory_id" class="form-select" required>
                                @foreach ($factories as $factory)
                                    <option value="{{ $factory->id }}">{{ $factory->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="to_factory_id" class="form-label mb-1">{{ __('inventory.to_factory') }}</label>
                            <select id="to_factory_id" name="to_factory_id" class="form-select" required>
                                @foreach ($factories as $factory)
                                    <option value="{{ $factory->id }}">{{ $factory->name }}</option>
                                @endforeach
                            </select>
                            @error('to_factory_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="notes" class="form-label mb-1">{{ __('inventory.notes') }}</label>
                            <input id="notes" name="notes" type="text" class="form-control" maxlength="2000">
                        </div>
                    </div>

                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label for="item_part" class="form-label mb-1">{{ __('inventory.spare_part') }}</label>
                            <select id="item_part" name="items[0][spare_part_id]" class="form-select" required
                                    data-tom-select>
                                <option value="">—</option>
                                @foreach ($spareParts as $part)
                                    <option value="{{ $part->id }}">{{ $part->part_number }} — {{ $part->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="item_bin" class="form-label mb-1">{{ __('inventory.from_bin') }}</label>
                            <select id="item_bin" name="items[0][from_bin_id]" class="form-select" required>
                                <option value="">—</option>
                                @foreach ($bins as $bin)
                                    <option value="{{ $bin->id }}">{{ $bin->code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="item_quantity" class="form-label mb-1">{{ __('inventory.quantity') }}</label>
                            <input id="item_quantity" name="items[0][quantity]" type="number" step="0.0001"
                                   min="0.0001" class="form-control" value="1" required>
                        </div>

                        <div class="col-md-1">
                            <button class="btn btn-info text-white w-100">{{ __('common.save') }}</button>
                        </div>
                    </div>

                    @error('items')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>
    @endcan
@endsection
