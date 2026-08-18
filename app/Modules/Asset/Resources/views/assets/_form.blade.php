@php
    $asset = $asset ?? null;
    $value = fn (string $field, $default = null) => old($field, $asset?->{$field} ?? $default);
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label for="asset_code" class="form-label">{{ __('asset.asset_code') }} <span class="text-danger">*</span></label>
        <input id="asset_code" name="asset_code" type="text" maxlength="64" required
               class="form-control @error('asset_code') is-invalid @enderror"
               value="{{ $value('asset_code') }}"
               @if ($asset) readonly @endif>
        @if ($asset)
            {{-- Immutable: it is printed on the machine and referenced by every
                 historical record. --}}
            <div class="form-text">{{ __('asset.asset_code') }} &mdash; {{ __('common.not_available') }}</div>
        @endif
        @error('asset_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-8">
        <label for="name" class="form-label">{{ __('asset.name') }} <span class="text-danger">*</span></label>
        <input id="name" name="name" type="text" maxlength="255" required
               class="form-control @error('name') is-invalid @enderror"
               value="{{ $value('name') }}">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="asset_type_id" class="form-label">{{ __('asset.type') }} <span class="text-danger">*</span></label>
        <select id="asset_type_id" name="asset_type_id" required
                class="form-select @error('asset_type_id') is-invalid @enderror">
            <option value="">&mdash;</option>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected($value('asset_type_id') === $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
        @error('asset_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="asset_category_id" class="form-label">{{ __('asset.category') }} <span class="text-danger">*</span></label>
        <select id="asset_category_id" name="asset_category_id" required
                class="form-select @error('asset_category_id') is-invalid @enderror">
            <option value="">&mdash;</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}"
                        data-type="{{ $category->asset_type_id }}"
                        @selected($value('asset_category_id') === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('asset_category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="criticality" class="form-label">{{ __('asset.criticality') }} <span class="text-danger">*</span></label>
        <select id="criticality" name="criticality" required
                class="form-select @error('criticality') is-invalid @enderror">
            @foreach ($criticalities as $criticality)
                <option value="{{ $criticality }}" @selected($value('criticality', 'MEDIUM') === $criticality)>
                    {{ __('asset.criticality_'.strtolower($criticality)) }}
                </option>
            @endforeach
        </select>
        @error('criticality') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="manufacturer_id" class="form-label">{{ __('asset.manufacturer') }}</label>
        <select id="manufacturer_id" name="manufacturer_id" class="form-select">
            <option value="">&mdash;</option>
            @foreach ($manufacturers as $manufacturer)
                <option value="{{ $manufacturer->id }}" @selected($value('manufacturer_id') === $manufacturer->id)>
                    {{ $manufacturer->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label for="serial_number" class="form-label">{{ __('asset.serial_number') }}</label>
        <input id="serial_number" name="serial_number" type="text" maxlength="128"
               class="form-control @error('serial_number') is-invalid @enderror"
               value="{{ $value('serial_number') }}">
        @error('serial_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="barcode" class="form-label">{{ __('asset.barcode') }}</label>
        <input id="barcode" name="barcode" type="text" maxlength="64" class="form-control"
               value="{{ $value('barcode') }}">
    </div>

    <div class="col-md-6">
        <label for="current_factory_id" class="form-label">{{ __('asset.factory') }} <span class="text-danger">*</span></label>
        <select id="current_factory_id" name="current_factory_id" required
                class="form-select @error('current_factory_id') is-invalid @enderror"
                @if ($asset) disabled @endif>
            <option value="">&mdash;</option>
            @foreach ($factories as $factory)
                <option value="{{ $factory->id }}" @selected($value('current_factory_id') === $factory->id)>
                    {{ $factory->name }}
                </option>
            @endforeach
        </select>
        @if ($asset)
            {{-- Moving between factories is a transfer, so the movement is
                 recorded rather than silently overwritten. --}}
            <input type="hidden" name="current_factory_id" value="{{ $asset->current_factory_id }}">
            <div class="form-text">{{ __('asset.factory_change_needs_transfer') }}</div>
        @endif
        @error('current_factory_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="asset_location_id" class="form-label">{{ __('asset.location') }} <span class="text-danger">*</span></label>
        <select id="asset_location_id" name="asset_location_id" required
                class="form-select @error('asset_location_id') is-invalid @enderror"
                @if ($asset) disabled @endif>
            <option value="">&mdash;</option>
            @foreach ($locations as $location)
                <option value="{{ $location->id }}"
                        data-factory="{{ $location->factory_id }}"
                        @selected($value('asset_location_id') === $location->id)>{{ $location->name }}</option>
            @endforeach
        </select>
        @if ($asset)
            <input type="hidden" name="asset_location_id" value="{{ $asset->asset_location_id }}">
        @endif
        @error('asset_location_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">{{ __('asset.description') }}</label>
        <textarea id="description" name="description" rows="2" class="form-control">{{ $value('description') }}</textarea>
    </div>

    <div class="col-md-4">
        <label for="purchase_date" class="form-label">{{ __('asset.purchase_date') }}</label>
        <input id="purchase_date" name="purchase_date" type="date" class="form-control @error('purchase_date') is-invalid @enderror"
               value="{{ $asset?->purchase_date?->toDateString() ?? old('purchase_date') }}">
        @error('purchase_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="installation_date" class="form-label">{{ __('asset.installation_date') }}</label>
        <input id="installation_date" name="installation_date" type="date" class="form-control @error('installation_date') is-invalid @enderror"
               value="{{ $asset?->installation_date?->toDateString() ?? old('installation_date') }}">
        @error('installation_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="commissioning_date" class="form-label">{{ __('asset.commissioning_date') }}</label>
        <input id="commissioning_date" name="commissioning_date" type="date" class="form-control @error('commissioning_date') is-invalid @enderror"
               value="{{ $asset?->commissioning_date?->toDateString() ?? old('commissioning_date') }}">
        @error('commissioning_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @can('asset.financial.view')
        <div class="col-md-4">
            <label for="acquisition_cost" class="form-label">{{ __('asset.acquisition_cost') }}</label>
            <input id="acquisition_cost" name="acquisition_cost" type="number" step="0.0001" min="0"
                   class="form-control @error('acquisition_cost') is-invalid @enderror"
                   value="{{ $value('acquisition_cost') }}">
            @error('acquisition_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
            <label for="installation_cost" class="form-label">{{ __('asset.installation_cost') }}</label>
            <input id="installation_cost" name="installation_cost" type="number" step="0.0001" min="0"
                   class="form-control" value="{{ $value('installation_cost') }}">
        </div>

        <div class="col-md-4">
            <label for="currency" class="form-label">{{ __('asset.currency') }}</label>
            <input id="currency" name="currency" type="text" maxlength="3"
                   class="form-control @error('currency') is-invalid @enderror"
                   value="{{ $value('currency', 'BDT') }}">
            @error('currency') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endcan

    <div class="col-md-6">
        <label for="warranty_start" class="form-label">{{ __('asset.warranty_start') }}</label>
        <input id="warranty_start" name="warranty_start" type="date" class="form-control"
               value="{{ $asset?->warranty_start?->toDateString() ?? old('warranty_start') }}">
    </div>

    <div class="col-md-6">
        <label for="warranty_end" class="form-label">{{ __('asset.warranty_end') }}</label>
        <input id="warranty_end" name="warranty_end" type="date" class="form-control @error('warranty_end') is-invalid @enderror"
               value="{{ $asset?->warranty_end?->toDateString() ?? old('warranty_end') }}">
        @error('warranty_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
