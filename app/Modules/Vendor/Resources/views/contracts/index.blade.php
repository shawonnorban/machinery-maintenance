@extends('layouts.app')
@section('title', __('vendor.contracts'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('vendor.contracts') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('vendor.contracts')">
        <x-slot:actions>
            @can('create', App\Modules\Vendor\Models\ServiceContract::class)
                <a href="{{ route('app.service-contracts.create') }}" class="btn btn-sm btn-info text-white">
                    {{ __('vendor.new_contract') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-sm-6 col-xl-3">
            <x-kpi-tile :label="__('vendor.expiring_soon')"
                        :value="number_format($expiringCount)"
                        :tone="$expiringCount > 0 ? 'warning' : 'success'" />
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link {{ request()->boolean('expiring') ? 'active' : '' }}"
               href="{{ route('app.service-contracts.index', ['expiring' => 1]) }}">{{ __('vendor.expiring_soon') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->boolean('expiring') ? '' : 'active' }}"
               href="{{ route('app.service-contracts.index') }}">{{ __('common.all') }}</a>
        </li>
    </ul>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('vendor.contract_number') }}</th>
                        <th>{{ __('vendor.vendor') }}</th>
                        <th>{{ __('vendor.scope') }}</th>
                        <th>{{ __('vendor.end_date') }}</th>
                        <th class="text-end">{{ __('vendor.value') }}</th>
                        <th>{{ __('vendor.status') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($contracts as $contract)
                        @php $days = $contract->daysRemaining(); @endphp

                        <tr class="{{ $days >= 0 && $days <= 30 && $contract->status === 'ACTIVE' ? 'table-warning' : '' }}">
                            <td>
                                <a href="{{ route('app.service-contracts.show', $contract) }}">
                                    {{ $contract->contract_number }}
                                </a>
                                <div class="small text-body-secondary">
                                    {{ __('vendor.contract_type_'.strtolower($contract->contract_type)) }}
                                </div>
                            </td>
                            <td>{{ $contract->vendor?->name }}</td>
                            <td>
                                @if ($contract->asset_id)
                                    {{ $contract->asset?->asset_code }}
                                @elseif ($contract->factory_id)
                                    {{ $contract->factory?->name }}
                                @else
                                    {{ __('vendor.scope_list') }}
                                @endif
                            </td>
                            <td>{{ $contract->end_date->format('Y-m-d') }}</td>
                            <td class="text-end">{{ $contract->value === null ? '—' : number_format((float) $contract->value, 0) }}</td>
                            <td>
                                <x-status-pill :status="$contract->status" :tone="match ($contract->status) {
                                    'ACTIVE' => 'success',
                                    'CANCELLED' => 'danger',
                                    'RENEWED' => 'info',
                                    default => 'secondary',
                                }">
                                    {{ __('vendor.contract_status_'.strtolower($contract->status)) }}
                                </x-status-pill>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state :title="__('vendor.no_contracts')"
                                               :description="__('vendor.no_contracts_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $contracts->links() }}
@endsection
