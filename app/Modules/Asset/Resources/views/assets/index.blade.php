@extends('layouts.app')
@section('title', __('asset.assets'))

@section('content')
    <x-page-header :title="__('asset.assets')"
                   :subtitle="__('asset.showing', [
                       'from' => $assets->firstItem() ?? 0,
                       'to' => $assets->lastItem() ?? 0,
                       'total' => $assets->total(),
                   ])">
        <x-slot:actions>
            @can('create', App\Modules\Asset\Models\Asset::class)
                <a href="{{ route('app.assets.create') }}" class="btn btn-primary">
                    {{ __('asset.new_asset') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4">
        <div class="card-body">
            {{-- Filters are query-string driven and applied server-side. A
                 tenant with 20,000 assets cannot ship them all to the browser
                 (Frontend 9.3). --}}
            <form method="GET" action="{{ route('app.assets.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label">{{ __('common.search') }}</label>
                    <input id="search" name="search" type="search" class="form-control"
                           value="{{ request('search') }}"
                           placeholder="{{ __('asset.search_placeholder') }}">
                </div>

                <div class="col-md-2">
                    <label for="status" class="form-label">{{ __('asset.status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">&mdash;</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>
                                {{ __('asset.status_'.strtolower($status)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="criticality" class="form-label">{{ __('asset.criticality') }}</label>
                    <select id="criticality" name="criticality" class="form-select">
                        <option value="">&mdash;</option>
                        @foreach ($criticalities as $criticality)
                            <option value="{{ $criticality }}" @selected(request('criticality') === $criticality)>
                                {{ __('asset.criticality_'.strtolower($criticality)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="asset_type_id" class="form-label">{{ __('asset.type') }}</label>
                    <select id="asset_type_id" name="asset_type_id" class="form-select">
                        <option value="">&mdash;</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected(request('asset_type_id') === $type->id)>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">{{ __('asset.filter') }}</button>
                    <a href="{{ route('app.assets.index') }}" class="btn btn-outline-secondary">{{ __('asset.clear') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if ($assets->isEmpty())
                <x-empty-state :title="__('asset.no_assets')" :description="__('asset.no_assets_hint')">
                    <x-slot:action>
                        @can('create', App\Modules\Asset\Models\Asset::class)
                            <a href="{{ route('app.assets.create') }}" class="btn btn-primary">
                                {{ __('asset.new_asset') }}
                            </a>
                        @endcan
                    </x-slot:action>
                </x-empty-state>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-sticky-head">
                        <thead>
                            <tr>
                                <th>@include('asset::assets._sort', ['column' => 'asset_code', 'label' => __('asset.asset_code')])</th>
                                <th>@include('asset::assets._sort', ['column' => 'name', 'label' => __('asset.name')])</th>
                                <th>{{ __('asset.type') }}</th>
                                <th>{{ __('asset.factory') }}</th>
                                <th>{{ __('asset.location') }}</th>
                                <th>@include('asset::assets._sort', ['column' => 'criticality', 'label' => __('asset.criticality')])</th>
                                <th>@include('asset::assets._sort', ['column' => 'status', 'label' => __('asset.status')])</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assets as $asset)
                                <tr>
                                    <td>
                                        <a href="{{ route('app.assets.show', $asset) }}" class="fw-semibold text-decoration-none">
                                            {{ $asset->asset_code }}
                                        </a>
                                    </td>
                                    <td>{{ $asset->name }}</td>
                                    <td class="small">{{ $asset->type?->name }}</td>
                                    <td class="small">{{ $asset->factory?->name }}</td>
                                    <td class="small text-body-secondary">{{ $asset->location?->name }}</td>
                                    <td>@include('asset::assets._criticality', ['criticality' => $asset->criticality])</td>
                                    <td>@include('asset::assets._status', ['status' => $asset->status])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($assets->hasPages())
            <div class="card-footer">{{ $assets->links() }}</div>
        @endif
    </div>
@endsection
