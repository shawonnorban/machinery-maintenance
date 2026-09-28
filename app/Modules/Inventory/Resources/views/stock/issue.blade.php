@extends('layouts.app')
@section('title', __('inventory.issue_return'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('inventory.issue_return') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('inventory.issue_return')" :subtitle="__('inventory.issue_return_intro')" />

    {{-- Said plainly, because it is the one thing that surprises people: stock
         handed out here is not charged to any machine. Anything fitted to one
         goes through its work order, which is where the cost belongs and where
         the failure history can find it. --}}
    <div class="alert alert-warning">{{ __('inventory.issue_no_asset_warning') }}</div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('app.inventory.issue.store') }}" class="row g-2 align-items-end">
                @csrf

                <div class="col-md-4">
                    <label for="spare_part_id" class="form-label mb-1">{{ __('inventory.spare_part') }}</label>
                    <select id="spare_part_id" name="spare_part_id" class="form-select form-select-sm" required
                            data-tom-select>
                        <option value="">—</option>
                        @foreach ($parts as $part)
                            <option value="{{ $part->id }}">{{ $part->part_number }} — {{ $part->name }}</option>
                        @endforeach
                    </select>
                    @error('spare_part_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="bin_id" class="form-label mb-1">{{ __('inventory.bin') }}</label>
                    <select id="bin_id" name="bin_id" class="form-select form-select-sm" required>
                        <option value="">—</option>
                        @foreach ($bins as $bin)
                            <option value="{{ $bin->id }}">{{ $bin->fullPath() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="transaction_type" class="form-label mb-1">{{ __('inventory.movement') }}</label>
                    <select id="transaction_type" name="transaction_type" class="form-select form-select-sm" required>
                        <option value="ISSUE">{{ __('inventory.issue_out') }}</option>
                        <option value="RETURN">{{ __('inventory.return_in') }}</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="quantity" class="form-label mb-1">{{ __('inventory.quantity') }}</label>
                    <input id="quantity" name="quantity" type="number" step="0.0001" min="0.0001"
                           class="form-control form-control-sm" value="1" required>
                    @error('quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-1">
                    <button class="btn btn-sm btn-info text-white w-100">{{ __('common.save') }}</button>
                </div>

                <div class="col-12">
                    <label for="notes" class="form-label mb-1">{{ __('inventory.who_and_why') }}</label>
                    <input id="notes" name="notes" type="text" class="form-control form-control-sm"
                           required maxlength="2000" placeholder="{{ __('inventory.who_and_why_example') }}">
                    {{-- Required, not optional: stock that moves with no work
                         order behind it and no explanation is indistinguishable
                         from loss. --}}
                    @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('inventory.recent_movements') }}</div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('inventory.spare_part') }}</th>
                        <th>{{ __('inventory.bin') }}</th>
                        <th>{{ __('inventory.movement') }}</th>
                        <th class="text-end">{{ __('inventory.quantity') }}</th>
                        <th>{{ __('inventory.who_and_why') }}</th>
                        <th>{{ __('inventory.when') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td>
                                <a href="{{ route('app.inventory.parts.show', $movement->spare_part_id) }}">
                                    {{ $movement->sparePart?->part_number }}
                                </a>
                            </td>
                            <td class="small">{{ $movement->bin?->code }}</td>
                            <td>
                                <x-status-pill :status="$movement->transaction_type"
                                               :tone="$movement->transaction_type === 'RETURN' ? 'success' : 'secondary'">
                                    {{ $movement->transaction_type === 'RETURN'
                                        ? __('inventory.return_in')
                                        : __('inventory.issue_out') }}
                                </x-status-pill>
                            </td>
                            <td class="text-end">{{ $movement->quantity }}</td>
                            <td class="small text-body-secondary">{{ $movement->notes }}</td>
                            <td class="small">@dt($movement->transaction_at)</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state :title="__('inventory.no_direct_movements')"
                                               :description="__('inventory.no_direct_movements_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
