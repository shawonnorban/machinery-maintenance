@extends('layouts.app')
@section('title', __('vendor.new_warranty'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.warranties.index') }}">{{ __('vendor.warranties') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('vendor.new_warranty') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('vendor.new_warranty')" />

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('app.warranties.store') }}" class="row g-3">
                @csrf

                <div class="col-md-6">
                    <label for="asset_id" class="form-label">{{ __('report.columns.asset_code') }}</label>
                    <select class="form-select @error('asset_id') is-invalid @enderror" id="asset_id"
                            name="asset_id" data-tom-select required>
                        <option value="">—</option>
                        @foreach ($assets as $asset)
                            <option value="{{ $asset->id }}" @selected(old('asset_id', $assetId) === $asset->id)>
                                {{ $asset->asset_code }} — {{ $asset->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('asset_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="vendor_id" class="form-label">{{ __('vendor.vendor') }}</label>
                    <select class="form-select" id="vendor_id" name="vendor_id">
                        <option value="">—</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected(old('vendor_id') === $vendor->id)>
                                {{ $vendor->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="warranty_type" class="form-label">{{ __('vendor.warranty_type') }}</label>
                    <select class="form-select" id="warranty_type" name="warranty_type" required>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" @selected(old('warranty_type') === $type)>
                                {{ __('vendor.type_'.strtolower($type === 'SERVICE' ? 'service_warranty' : $type)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="start_date" class="form-label">{{ __('vendor.start_date') }}</label>
                    <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                           id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="end_date" class="form-label">{{ __('vendor.end_date') }}</label>
                    <input type="date" class="form-control @error('end_date') is-invalid @enderror"
                           id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                    @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="reference" class="form-label">{{ __('vendor.reference') }}</label>
                    <input type="text" class="form-control" id="reference" name="reference"
                           value="{{ old('reference') }}">
                </div>

                <div class="col-md-4">
                    <label for="coverage" class="form-label">{{ __('vendor.coverage') }}</label>
                    <textarea class="form-control" id="coverage" name="coverage" rows="2">{{ old('coverage') }}</textarea>
                </div>

                <div class="col-md-4">
                    <label for="exclusions" class="form-label">{{ __('vendor.exclusions') }}</label>
                    <textarea class="form-control" id="exclusions" name="exclusions" rows="2">{{ old('exclusions') }}</textarea>
                    <div class="form-text">{{ __('vendor.exclusions_hint') }}</div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-info text-white">{{ __('common.save') }}</button>
                    <a href="{{ route('app.warranties.index') }}" class="btn btn-outline-secondary">
                        {{ __('common.cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
