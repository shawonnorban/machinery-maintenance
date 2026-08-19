@extends('layouts.app')
@section('title', __('vendor.warranties'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('vendor.warranties') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('vendor.warranties')">
        <x-slot:actions>
            @can('create', App\Modules\Vendor\Models\Warranty::class)
                <a href="{{ route('app.warranties.create') }}" class="btn btn-sm btn-info text-white">
                    {{ __('vendor.new_warranty') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-sm-6 col-xl-3">
            {{-- What is about to run out leads, because that is the only part
                 of this screen anybody can still act on. --}}
            <x-kpi-tile :label="__('vendor.expiring_soon')"
                        :value="number_format($expiringCount)"
                        :tone="$expiringCount > 0 ? 'warning' : 'success'" />
        </div>

        <div class="col-sm-6 col-xl-3">
            <x-kpi-tile :label="__('vendor.open_claims')" :value="number_format($openClaims)" tone="info" />
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link {{ request()->boolean('expiring') ? 'active' : '' }}"
               href="{{ route('app.warranties.index', ['expiring' => 1]) }}">{{ __('vendor.expiring_soon') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->boolean('expiring') ? '' : 'active' }}"
               href="{{ route('app.warranties.index') }}">{{ __('common.all') }}</a>
        </li>
    </ul>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('report.columns.asset_code') }}</th>
                        <th>{{ __('vendor.vendor') }}</th>
                        <th>{{ __('vendor.warranty_type') }}</th>
                        <th>{{ __('vendor.end_date') }}</th>
                        <th class="text-end">{{ __('vendor.days_remaining') }}</th>
                        <th>{{ __('vendor.status') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($warranties as $warranty)
                        @php $days = $warranty->daysRemaining(); @endphp

                        <tr class="{{ $days >= 0 && $days <= 30 ? 'table-warning' : '' }}">
                            <td>
                                <a href="{{ route('app.warranties.show', $warranty) }}">
                                    {{ $warranty->asset?->asset_code }}
                                </a>
                                <div class="small text-body-secondary">{{ $warranty->asset?->name }}</div>
                            </td>
                            <td>{{ $warranty->vendor?->name ?? __('vendor.unnamed_vendor') }}</td>
                            <td>{{ __('vendor.type_'.strtolower($warranty->warranty_type === 'SERVICE' ? 'service_warranty' : $warranty->warranty_type)) }}</td>
                            <td>{{ $warranty->end_date->format('Y-m-d') }}</td>
                            <td class="text-end">
                                @if ($days < 0)
                                    <span class="text-body-secondary">{{ __('vendor.expired_days_ago', ['days' => abs($days)]) }}</span>
                                @else
                                    {{ number_format($days) }}
                                @endif
                            </td>
                            <td>
                                <x-status-pill :status="$warranty->status"
                                               :tone="$warranty->isActiveOn() ? 'success' : 'secondary'">
                                    {{ __('vendor.warranty_status_'.strtolower($warranty->status)) }}
                                </x-status-pill>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state :title="__('vendor.no_warranties')"
                                               :description="__('vendor.no_warranties_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $warranties->links() }}
@endsection
