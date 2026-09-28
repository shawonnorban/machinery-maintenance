@extends('layouts.app')
@section('title', __('vendor.vendors'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('vendor.vendors') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('vendor.vendors')">
        <x-slot:actions>
            @can('create', App\Modules\Vendor\Models\Vendor::class)
                <a href="{{ route('app.vendors.create') }}" class="btn btn-sm btn-info text-white">
                    {{ __('vendor.new_vendor') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-sm-5">
                    <input type="search" class="form-control" name="q" value="{{ request('q') }}"
                           placeholder="{{ __('vendor.search') }}">
                </div>

                <div class="col-sm-3">
                    <select class="form-select" name="type">
                        <option value="">{{ __('vendor.type') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>
                                {{ __('vendor.type_'.strtolower($type)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-3">
                    <select class="form-select" name="status">
                        <option value="">{{ __('vendor.status') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>
                                {{ __('vendor.status_'.strtolower($status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-1">
                    <button class="btn btn-outline-secondary w-100">{{ __('common.search') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('vendor.name') }}</th>
                        <th>{{ __('vendor.type') }}</th>
                        <th>{{ __('vendor.contact_name') }}</th>
                        <th class="text-end">{{ __('vendor.warranties') }}</th>
                        <th class="text-end">{{ __('vendor.contracts') }}</th>
                        <th>{{ __('vendor.status') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($vendors as $vendor)
                        <tr>
                            <td>
                                <a href="{{ route('app.vendors.show', $vendor) }}">{{ $vendor->name }}</a>
                                <div class="small text-body-secondary">{{ $vendor->code }}</div>
                            </td>
                            <td>{{ __('vendor.type_'.strtolower($vendor->vendor_type)) }}</td>
                            <td>
                                {{ $vendor->contact_name }}
                                <div class="small text-body-secondary">{{ $vendor->phone }}</div>
                            </td>
                            <td class="text-end">{{ number_format($vendor->warranties_count) }}</td>
                            <td class="text-end">{{ number_format($vendor->contracts_count) }}</td>
                            <td>
                                <x-status-pill :status="$vendor->status" :tone="match ($vendor->status) {
                                    'ACTIVE' => 'success',
                                    'BLACKLISTED' => 'danger',
                                    default => 'secondary',
                                }">
                                    {{ __('vendor.status_'.strtolower($vendor->status)) }}
                                </x-status-pill>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state :title="__('vendor.no_vendors')"
                                               :description="__('vendor.no_vendors_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $vendors->links() }}
@endsection
