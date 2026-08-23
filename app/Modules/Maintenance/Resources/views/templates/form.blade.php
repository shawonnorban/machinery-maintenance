@extends('layouts.app')
@section('title', $template ? $template->name : __('maintenance.new_template'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('app.maintenance.templates') }}">{{ __('maintenance.templates') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ $template ? $template->name : __('maintenance.new_template') }}
    </li>
@endsection

@section('content')
    <x-page-header :title="$template ? __('maintenance.edit_template') : __('maintenance.new_template')"
                   :subtitle="__('maintenance.template_intro')">
        <x-slot:actions>
            <a href="{{ route('app.maintenance.templates') }}" class="btn btn-sm btn-outline-secondary">
                <i class="cil-arrow-left" aria-hidden="true"></i> {{ __('common.back') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST"
          action="{{ $template
              ? route('app.maintenance.templates.update', $template->id)
              : route('app.maintenance.templates.store') }}">
        @csrf
        @if ($template)
            @method('PATCH')
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label">{{ __('maintenance.template_name') }}</label>
                        <input id="name" name="name" type="text" class="form-control"
                               value="{{ old('name', $template?->name) }}" required maxlength="255">
                        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="code" class="form-label">{{ __('maintenance.code') }}</label>
                        @if ($template)
                            {{-- The code ties every version of a checklist
                                 together, so it is set once. --}}
                            <input id="code" type="text" class="form-control" value="{{ $template->code }}" disabled>
                        @else
                            <input id="code" name="code" type="text" class="form-control text-uppercase"
                                   value="{{ old('code') }}" required maxlength="64">
                        @endif
                        @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="asset_type_id" class="form-label">{{ __('maintenance.for_asset_type') }}</label>
                        <select id="asset_type_id" name="asset_type_id" class="form-select">
                            <option value="">{{ __('maintenance.any_asset_type') }}</option>
                            @foreach ($assetTypes as $type)
                                <option value="{{ $type->id }}"
                                        @selected(old('asset_type_id', $template?->asset_type_id) === $type->id)>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="maintenance_type_id" class="form-label">{{ __('maintenance.maintenance_type') }}</label>
                        <select id="maintenance_type_id" name="maintenance_type_id" class="form-select">
                            <option value="">—</option>
                            @foreach ($maintenanceTypes as $type)
                                <option value="{{ $type->id }}"
                                        @selected(old('maintenance_type_id', $template?->maintenance_type_id) === $type->id)>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @unless ($template)
                        <div class="col-md-4">
                            <label for="estimated_duration_minutes" class="form-label">
                                {{ __('maintenance.estimated_minutes') }}
                            </label>
                            <input id="estimated_duration_minutes" name="estimated_duration_minutes" type="number"
                                   min="1" max="10080" class="form-control"
                                   value="{{ old('estimated_duration_minutes') }}">
                        </div>
                    @endunless

                    <div class="col-12">
                        <label for="description" class="form-label">{{ __('maintenance.description') }}</label>
                        <textarea id="description" name="description" class="form-control" rows="2"
                                  maxlength="2000">{{ old('description', $template?->description) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-info text-white">
                {{ $template ? __('common.save') : __('maintenance.create_and_add_checks') }}
            </button>
            <a href="{{ route('app.maintenance.templates') }}" class="btn btn-outline-secondary">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>
@endsection
