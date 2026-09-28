@extends('layouts.app')
@section('title', $vendor->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.vendors.index') }}">{{ __('vendor.vendors') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $vendor->name }}</li>
@endsection

@section('content')
    <x-page-header :title="$vendor->name" :subtitle="$vendor->code">
        <x-slot:actions>
            @can('update', $vendor)
                <a href="{{ route('app.vendors.edit', $vendor) }}" class="btn btn-sm btn-outline-secondary">
                    {{ __('common.edit') }}
                </a>

                <form method="POST" action="{{ route('app.vendors.archive', $vendor) }}"
                      onsubmit="return confirm('{{ __('vendor.archive_confirm') }}')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">{{ __('vendor.archive') }}</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('vendor.type') }}</dt>
                        <dd class="col-7">{{ __('vendor.type_'.strtolower($vendor->vendor_type)) }}</dd>

                        <dt class="col-5">{{ __('vendor.status') }}</dt>
                        <dd class="col-7">
                            <x-status-pill :status="$vendor->status" :tone="match ($vendor->status) {
                                'ACTIVE' => 'success',
                                'BLACKLISTED' => 'danger',
                                default => 'secondary',
                            }">
                                {{ __('vendor.status_'.strtolower($vendor->status)) }}
                            </x-status-pill>
                        </dd>

                        <dt class="col-5">{{ __('vendor.contact_name') }}</dt>
                        <dd class="col-7">{{ $vendor->contact_name ?: '—' }}</dd>

                        <dt class="col-5">{{ __('vendor.phone') }}</dt>
                        <dd class="col-7">{{ $vendor->phone ?: '—' }}</dd>

                        <dt class="col-5">{{ __('vendor.email') }}</dt>
                        <dd class="col-7">{{ $vendor->email ?: '—' }}</dd>

                        <dt class="col-5">{{ __('vendor.tax_reference') }}</dt>
                        <dd class="col-7">{{ $vendor->tax_reference ?: '—' }}</dd>

                        <dt class="col-5">{{ __('vendor.address') }}</dt>
                        <dd class="col-7">{{ $vendor->address ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-shield-alt" aria-hidden="true"></i>
                    <span>{{ __('vendor.warranties') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                            @forelse ($warranties as $warranty)
                                <tr>
                                    <td>
                                        <a href="{{ route('app.warranties.show', $warranty) }}">
                                            {{ $warranty->asset?->asset_code }}
                                        </a>
                                        <div class="small text-body-secondary">{{ $warranty->asset?->name }}</div>
                                    </td>
                                    <td>{{ $warranty->start_date->format('Y-m-d') }} — {{ $warranty->end_date->format('Y-m-d') }}</td>
                                    <td>
                                        <x-status-pill :status="$warranty->status"
                                                       :tone="$warranty->isActiveOn() ? 'success' : 'secondary'">
                                            {{ __('vendor.warranty_status_'.strtolower($warranty->status)) }}
                                        </x-status-pill>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-body-secondary">{{ __('vendor.no_warranties') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-description" aria-hidden="true"></i>
                    <span>{{ __('vendor.contracts') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <tbody>
                            @forelse ($contracts as $contract)
                                <tr>
                                    <td>
                                        <a href="{{ route('app.service-contracts.show', $contract) }}">
                                            {{ $contract->contract_number }}
                                        </a>
                                        <div class="small text-body-secondary">
                                            {{ $contract->asset?->asset_code ?? $contract->factory?->name ?? __('vendor.scope_list') }}
                                        </div>
                                    </td>
                                    <td>{{ $contract->start_date->format('Y-m-d') }} — {{ $contract->end_date->format('Y-m-d') }}</td>
                                    <td class="text-end">{{ $contract->value === null ? '—' : number_format((float) $contract->value, 0) }}</td>
                                    <td>
                                        <x-status-pill :status="$contract->status"
                                                       :tone="$contract->status === 'ACTIVE' ? 'success' : 'secondary'">
                                            {{ __('vendor.contract_status_'.strtolower($contract->status)) }}
                                        </x-status-pill>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-body-secondary">{{ __('vendor.no_contracts') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
