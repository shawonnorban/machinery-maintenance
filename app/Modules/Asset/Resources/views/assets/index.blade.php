@extends('layouts.app')
@section('title', __('asset.assets'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('asset.assets') }}</li>
@endsection

@section('content')
    {{-- One filter form drives search, the per-page select and the dropdowns,
         so changing any of them keeps the others (Frontend 9.3). --}}
    <form method="GET" action="{{ route('app.assets.index') }}" id="list-filter" class="mb-3">
        <div class="card">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="filter-search" class="form-label mb-1">{{ __('common.search') }}</label>
                        <input id="filter-search" name="search" type="search" class="form-control form-control-sm"
                               value="{{ request('search') }}" placeholder="{{ __('asset.search_placeholder') }}">
                    </div>

                    <div class="col-md-2">
                        <label for="status" class="form-label mb-1">{{ __('asset.status') }}</label>
                        <select id="status" name="status" class="form-select form-select-sm">
                            <option value="">{{ __('common.all') }}</option>
                            @foreach ($statuses as $option)
                                <option value="{{ $option }}" @selected(request('status') === $option)>
                                    {{ __('asset.status_'.strtolower($option)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="criticality" class="form-label mb-1">{{ __('asset.criticality') }}</label>
                        <select id="criticality" name="criticality" class="form-select form-select-sm">
                            <option value="">{{ __('common.all') }}</option>
                            @foreach ($criticalities as $option)
                                <option value="{{ $option }}" @selected(request('criticality') === $option)>
                                    {{ __('asset.criticality_'.strtolower($option)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="asset_type_id" class="form-label mb-1">{{ __('asset.type') }}</label>
                        <select id="asset_type_id" name="asset_type_id" class="form-select form-select-sm">
                            <option value="">{{ __('common.all') }}</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" @selected(request('asset_type_id') === $type->id)>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-info text-white">{{ __('asset.filter') }}</button>
                        <a href="{{ route('app.assets.index') }}" class="btn btn-sm btn-outline-secondary">
                            {{ __('asset.clear') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <x-data-table :title="__('asset.assets')" icon="cil-settings" :paginator="$assets">
        <x-slot:actions>
            @can('report.report.export')
                <a href="{{ route('app.assets.labels', request()->query()) }}" class="btn btn-sm btn-dark">
                    <i class="cil-print" aria-hidden="true"></i> {{ __('scan.labels') }}
                </a>
            @endcan

            @can('create', App\Modules\Asset\Models\Asset::class)
                <a href="{{ route('app.assets.create') }}" class="btn btn-sm btn-info text-white">
                    <i class="cil-plus" aria-hidden="true"></i> {{ __('common.add_new') }}
                </a>
            @endcan
        </x-slot:actions>

        <thead>
            <tr>
                <th class="col-index">{{ __('common.row_number') }}</th>
                <th>@include('asset::assets._sort', ['column' => 'asset_code', 'label' => __('asset.asset_code')])</th>
                <th>@include('asset::assets._sort', ['column' => 'name', 'label' => __('asset.name')])</th>
                <th>{{ __('asset.type') }}</th>
                <th>{{ __('asset.factory') }}</th>
                <th>{{ __('asset.location') }}</th>
                <th>@include('asset::assets._sort', ['column' => 'criticality', 'label' => __('asset.criticality')])</th>
                <th>@include('asset::assets._sort', ['column' => 'status', 'label' => __('asset.status')])</th>
                <th>{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($assets as $index => $asset)
                <tr>
                    <td class="col-index">{{ $assets->firstItem() + $index }}</td>
                    <td class="fw-semibold">{{ $asset->asset_code }}</td>
                    <td>{{ $asset->name }}</td>
                    <td>{{ $asset->type?->name }}</td>
                    <td>{{ $asset->factory?->name }}</td>
                    <td class="text-body-secondary">{{ $asset->location?->name }}</td>
                    <td>@include('asset::assets._criticality', ['criticality' => $asset->criticality])</td>
                    <td>@include('asset::assets._status', ['status' => $asset->status])</td>
                    <td>
                        <a href="{{ route('app.assets.show', $asset) }}"
                           class="btn btn-sm btn-info text-white btn-icon"
                           title="{{ __('common.view') }}" aria-label="{{ __('common.view') }}">
                            <i class="cil-eye" aria-hidden="true"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="p-0">
                        <x-empty-state :title="__('asset.no_assets')" :description="__('asset.no_assets_hint')">
                            <x-slot:action>
                                @can('create', App\Modules\Asset\Models\Asset::class)
                                    <a href="{{ route('app.assets.create') }}" class="btn btn-sm btn-info text-white">
                                        {{ __('asset.new_asset') }}
                                    </a>
                                @endcan
                            </x-slot:action>
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-data-table>
@endsection
