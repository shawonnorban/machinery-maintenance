@extends('layouts.app')
@section('title', __('asset.locations'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('asset.locations') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('asset.locations')" :subtitle="__('asset.locations_intro')">
        <x-slot:actions>
            <a href="{{ route('app.settings.locations.create') }}" class="btn btn-sm btn-info text-white">
                <i class="cil-plus" aria-hidden="true"></i> {{ __('asset.new_location') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-sm-5">
                    <input type="search" class="form-control" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('asset.location_search') }}">
                </div>

                <div class="col-sm-4">
                    <select class="form-select" name="factory_id">
                        <option value="">{{ __('asset.all_factories') }}</option>
                        @foreach ($factories as $factory)
                            <option value="{{ $factory->id }}" @selected(request('factory_id') === $factory->id)>
                                {{ $factory->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-3 d-flex gap-2">
                    <button class="btn btn-outline-secondary">{{ __('common.search') }}</button>
                    <a href="{{ route('app.settings.locations') }}" class="btn btn-outline-secondary">
                        {{ __('common.clear') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('asset.location_code') }}</th>
                        <th>{{ __('asset.location_path') }}</th>
                        <th class="text-end">{{ __('settings.machines') }}</th>
                        <th>{{ __('settings.status') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($locations as $location)
                        <tr @class(['opacity-50' => $location->status !== 'ACTIVE'])>
                            <td>
                                <code>{{ $location->code }}</code>
                                @if ($location->qr_code)
                                    <div class="small text-body-secondary">{{ $location->qr_code }}</div>
                                @endif
                            </td>
                            <td>{{ $location->full_path ?: $location->name }}</td>
                            <td class="text-end">{{ $location->assets_count }}</td>
                            <td>
                                <x-status-pill :status="$location->status"
                                               :tone="$location->status === 'ACTIVE' ? 'success' : 'secondary'">
                                    {{ $location->status === 'ACTIVE' ? __('settings.open') : __('settings.closed') }}
                                </x-status-pill>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('app.settings.locations.edit', $location) }}"
                                       class="btn btn-sm btn-outline-secondary btn-icon"
                                       title="{{ __('common.edit') }}" aria-label="{{ __('common.edit') }}">
                                        <i class="cil-pencil" aria-hidden="true"></i>
                                    </a>

                                    <form method="POST" action="{{ route('app.settings.locations.toggle', $location) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            {{ $location->status === 'ACTIVE' ? __('settings.close') : __('settings.reopen') }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('app.settings.locations.destroy', $location) }}"
                                          onsubmit="return confirm(@js(__('asset.location_delete_confirm', ['code' => $location->code])))">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}">
                                            <i class="cil-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state :title="__('asset.no_locations')"
                                               :description="__('asset.no_locations_hint')">
                                    <x-slot:action>
                                        <a href="{{ route('app.settings.locations.create') }}"
                                           class="btn btn-sm btn-info text-white">
                                            {{ __('asset.new_location') }}
                                        </a>
                                    </x-slot:action>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($locations->hasPages())
            <div class="card-footer">{{ $locations->links() }}</div>
        @endif
    </div>
@endsection
