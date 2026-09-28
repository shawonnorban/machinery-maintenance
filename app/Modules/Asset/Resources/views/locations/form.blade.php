@extends('layouts.app')
@section('title', $location ? $location->code : __('asset.new_location'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.settings.locations') }}">{{ __('asset.locations') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ $location ? $location->code : __('asset.new_location') }}
    </li>
@endsection

@section('content')
    <x-page-header :title="$location ? __('asset.edit_location') : __('asset.new_location')"
                   :subtitle="$location?->full_path">
        <x-slot:actions>
            <a href="{{ route('app.settings.locations') }}" class="btn btn-sm btn-outline-secondary">
                <i class="cil-arrow-left" aria-hidden="true"></i> {{ __('common.back') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST"
          action="{{ $location
              ? route('app.settings.locations.update', $location)
              : route('app.settings.locations.store') }}">
        @csrf
        @if ($location)
            @method('PATCH')
        @endif

        <div class="row">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-location-pin" aria-hidden="true"></i>
                        <span>{{ __('asset.location') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="factory_id" class="form-label">{{ __('asset.factory') }}</label>
                                <select id="factory_id" name="factory_id" class="form-select" required>
                                    <option value="">—</option>
                                    @foreach ($factories as $factory)
                                        <option value="{{ $factory->id }}"
                                                @selected(old('factory_id', $location?->factory_id) === $factory->id)>
                                            {{ $factory->name }} ({{ $factory->code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('factory_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="code" class="form-label">{{ __('asset.location_code') }}</label>
                                <input id="code" name="code" type="text" class="form-control text-uppercase"
                                       value="{{ old('code', $location?->code) }}" required maxlength="64">
                                @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label for="name" class="form-label">{{ __('asset.location_name') }}</label>
                                <input id="name" name="name" type="text" class="form-control"
                                       value="{{ old('name', $location?->name) }}" required maxlength="255">
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-sitemap" aria-hidden="true"></i>
                        <span>{{ __('asset.location_hierarchy') }}</span>
                    </div>
                    <div class="card-body">
                        {{-- Every level optional: a factory that tracks its floor as a
                             flat list of line codes is not forced to model a hierarchy
                             it does not use (ADR-052). --}}
                        <p class="small text-body-secondary">{{ __('asset.location_hierarchy_hint') }}</p>

                        @foreach ([
                            'building_id' => ['asset.building', $buildings],
                            'department_id' => ['asset.department', $departments],
                            'production_line_id' => ['asset.production_line', $lines],
                        ] as $field => [$label, $options])
                            <div class="mb-3">
                                <label for="{{ $field }}" class="form-label">{{ __($label) }}</label>
                                <select id="{{ $field }}" name="{{ $field }}" class="form-select"
                                        @disabled($options->isEmpty())>
                                    <option value="">—</option>
                                    @foreach ($options as $option)
                                        <option value="{{ $option->id }}"
                                                @selected(old($field, $location?->{$field}) === $option->id)>
                                            {{ $option->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-info text-white">{{ __('common.save') }}</button>
            <a href="{{ route('app.settings.locations') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
