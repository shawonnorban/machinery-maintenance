@extends('layouts.app')
@section('title', __('vendor.new_contract'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.service-contracts.index') }}">{{ __('vendor.contracts') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('vendor.new_contract') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('vendor.new_contract')" />

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('app.service-contracts.store') }}" class="row g-3">
                @csrf

                <div class="col-md-6">
                    <label for="vendor_id" class="form-label">{{ __('vendor.vendor') }}</label>
                    <select class="form-select @error('vendor_id') is-invalid @enderror" id="vendor_id"
                            name="vendor_id" required>
                        <option value="">—</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected(old('vendor_id') === $vendor->id)>
                                {{ $vendor->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('vendor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="contract_type" class="form-label">{{ __('vendor.contract_type') }}</label>
                    <select class="form-select" id="contract_type" name="contract_type" required>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('contract_type') === $type)>
                                {{ __('vendor.contract_type_'.strtolower($type)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="contract_number" class="form-label">{{ __('vendor.contract_number') }}</label>
                    <input type="text" class="form-control" id="contract_number" name="contract_number"
                           value="{{ old('contract_number') }}" placeholder="AMC-2026-0001">
                </div>

                <div class="col-12">
                    <div class="card">
                        <div class="card-header">{{ __('vendor.scope') }}</div>
                        <div class="card-body row g-3">
                            {{-- Three shapes, because an AMC is as often written
                                 over a whole line as over one machine. --}}
                            <div class="col-12">
                                <div class="form-text">{{ __('vendor.scope_hint') }}</div>
                            </div>

                            <div class="col-md-4">
                                <label for="asset_id" class="form-label">{{ __('vendor.scope_asset') }}</label>
                                <select class="form-select" id="asset_id" name="asset_id" data-tom-select>
                                    <option value="">—</option>
                                    @foreach ($assets as $asset)
                                        <option value="{{ $asset->id }}" @selected(old('asset_id') === $asset->id)>
                                            {{ $asset->asset_code }} — {{ $asset->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="factory_id" class="form-label">{{ __('vendor.scope_factory') }}</label>
                                <select class="form-select" id="factory_id" name="factory_id">
                                    <option value="">—</option>
                                    @foreach ($factories as $factory)
                                        <option value="{{ $factory->id }}" @selected(old('factory_id') === $factory->id)>
                                            {{ $factory->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="asset_ids" class="form-label">{{ __('vendor.scope_list') }}</label>
                                <select class="form-select" id="asset_ids" name="asset_ids[]" multiple data-tom-select>
                                    @foreach ($assets as $asset)
                                        <option value="{{ $asset->id }}">{{ $asset->asset_code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="start_date" class="form-label">{{ __('vendor.start_date') }}</label>
                    <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                           id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="end_date" class="form-label">{{ __('vendor.end_date') }}</label>
                    <input type="date" class="form-control @error('end_date') is-invalid @enderror"
                           id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="renewal_date" class="form-label">{{ __('vendor.renewal_date') }}</label>
                    <input type="date" class="form-control" id="renewal_date" name="renewal_date"
                           value="{{ old('renewal_date') }}">
                </div>

                <div class="col-md-3">
                    <label for="value" class="form-label">{{ __('vendor.value') }}</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="value" name="value"
                           value="{{ old('value') }}">
                </div>

                <div class="col-md-3">
                    <label for="visits_per_year" class="form-label">{{ __('vendor.visits_per_year') }}</label>
                    <input type="number" min="0" max="365" class="form-control" id="visits_per_year"
                           name="visits_per_year" value="{{ old('visits_per_year') }}">
                </div>

                <div class="col-md-3">
                    <label for="response_time_hours" class="form-label">{{ __('vendor.response_time_hours') }}</label>
                    <input type="number" min="0" class="form-control" id="response_time_hours"
                           name="response_time_hours" value="{{ old('response_time_hours') }}">
                </div>

                <div class="col-md-6">
                    <label for="coverage" class="form-label">{{ __('vendor.coverage') }}</label>
                    <textarea class="form-control" id="coverage" name="coverage" rows="2">{{ old('coverage') }}</textarea>
                </div>

                @error('scope')
                    <div class="col-12"><div class="alert alert-danger mb-0">{{ $message }}</div></div>
                @enderror

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-info text-white">{{ __('common.save') }}</button>
                    <a href="{{ route('app.service-contracts.index') }}" class="btn btn-outline-secondary">
                        {{ __('common.cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
