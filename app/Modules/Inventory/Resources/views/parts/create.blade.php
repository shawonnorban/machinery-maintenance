@extends('layouts.app')
@section('title', __('inventory.new_part'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.inventory.parts') }}">{{ __('inventory.spare_parts') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('inventory.new_part') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('inventory.new_part')" />

    <form method="POST" action="{{ route('app.inventory.parts.store') }}">
        @csrf

        <div class="row">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-list" aria-hidden="true"></i>
                        <span>{{ __('inventory.details') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="part_number" class="form-label">{{ __('inventory.part_number') }}</label>
                                <input id="part_number" name="part_number" type="text" class="form-control"
                                       value="{{ old('part_number') }}" required maxlength="64">
                                @error('part_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="unit" class="form-label">{{ __('inventory.unit') }}</label>
                                <select id="unit" name="unit" class="form-select" required>
                                    @foreach (App\Modules\Inventory\Models\SparePart::UNITS as $unit)
                                        <option value="{{ $unit }}" @selected(old('unit', 'PCS') === $unit)>{{ $unit }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="name" class="form-label">{{ __('inventory.name') }}</label>
                                <input id="name" name="name" type="text" class="form-control"
                                       value="{{ old('name') }}" required maxlength="255">
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="category_id" class="form-label">{{ __('inventory.category') }}</label>
                                <select id="category_id" name="category_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(old('category_id') === $category->id)>
                                            {{ $category->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="brand" class="form-label">{{ __('inventory.brand') }}</label>
                                <input id="brand" name="brand" type="text" class="form-control"
                                       value="{{ old('brand') }}" maxlength="255">
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">{{ __('inventory.notes') }}</label>
                                <textarea id="notes" name="notes" class="form-control" rows="2"
                                          maxlength="2000">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-storage" aria-hidden="true"></i>
                        <span>{{ __('inventory.stock') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="minimum_stock" class="form-label">{{ __('inventory.minimum_stock') }}</label>
                                <input id="minimum_stock" name="minimum_stock" type="number" step="0.0001" min="0"
                                       class="form-control" value="{{ old('minimum_stock', '0') }}">
                            </div>

                            <div class="col-md-6">
                                <label for="reorder_level" class="form-label">{{ __('inventory.reorder_level') }}</label>
                                <input id="reorder_level" name="reorder_level" type="number" step="0.0001" min="0"
                                       class="form-control" value="{{ old('reorder_level', '0') }}">
                            </div>

                            <div class="col-12">
                                {{-- Below the reorder level is the useful signal.
                                     By the time stock is out the lead time has
                                     already been lost. --}}
                                <div class="form-text">{{ __('inventory.reorder_hint') }}</div>
                            </div>

                            <div class="col-md-6">
                                <label for="lead_time_days" class="form-label">{{ __('inventory.lead_time_days') }}</label>
                                <input id="lead_time_days" name="lead_time_days" type="number" min="0" max="3650"
                                       class="form-control" value="{{ old('lead_time_days') }}">
                            </div>

                            <div class="col-md-6">
                                <label for="shelf_life_days" class="form-label">{{ __('inventory.shelf_life_days') }}</label>
                                <input id="shelf_life_days" name="shelf_life_days" type="number" min="0" max="36500"
                                       class="form-control" value="{{ old('shelf_life_days') }}">
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input id="is_critical_spare" name="is_critical_spare" type="checkbox" value="1"
                                           class="form-check-input" @checked(old('is_critical_spare'))>
                                    <label for="is_critical_spare" class="form-check-label">
                                        {{ __('inventory.is_critical_spare') }}
                                    </label>
                                    <div class="form-text">{{ __('inventory.critical_hint') }}</div>
                                </div>

                                <div class="form-check mt-2">
                                    <input id="hazardous" name="hazardous" type="checkbox" value="1"
                                           class="form-check-input" @checked(old('hazardous'))>
                                    <label for="hazardous" class="form-check-label">{{ __('inventory.hazardous') }}</label>
                                </div>
                            </div>

                            <div class="col-12">
                                {{-- No opening quantity here on purpose: stock
                                     enters through the ledger, so every unit has a
                                     movement behind it. --}}
                                <div class="form-text">{{ __('inventory.threshold_note') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-info text-white">{{ __('inventory.new_part') }}</button>
            <a href="{{ route('app.inventory.parts') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
