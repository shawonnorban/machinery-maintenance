@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">{{ __('vendor.name') }}</label>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
               value="{{ old('name', $vendor->name ?? '') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="code" class="form-label">{{ __('vendor.code') }}</label>
        <input type="text" class="form-control @error('code') is-invalid @enderror" id="code" name="code"
               value="{{ old('code', $vendor->code ?? '') }}" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label for="vendor_type" class="form-label">{{ __('vendor.type') }}</label>
        <select class="form-select" id="vendor_type" name="vendor_type" required>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('vendor_type', $vendor->vendor_type ?? 'SUPPLIER') === $type)>
                    {{ __('vendor.type_'.strtolower($type)) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4">
        <label for="contact_name" class="form-label">{{ __('vendor.contact_name') }}</label>
        <input type="text" class="form-control" id="contact_name" name="contact_name"
               value="{{ old('contact_name', $vendor->contact_name ?? '') }}">
    </div>

    <div class="col-md-4">
        <label for="phone" class="form-label">{{ __('vendor.phone') }}</label>
        <input type="text" class="form-control" id="phone" name="phone"
               value="{{ old('phone', $vendor->phone ?? '') }}">
    </div>

    <div class="col-md-4">
        <label for="email" class="form-label">{{ __('vendor.email') }}</label>
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
               value="{{ old('email', $vendor->email ?? '') }}">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label for="address" class="form-label">{{ __('vendor.address') }}</label>
        <textarea class="form-control" id="address" name="address" rows="2">{{ old('address', $vendor->address ?? '') }}</textarea>
    </div>

    <div class="col-md-4">
        <label for="tax_reference" class="form-label">{{ __('vendor.tax_reference') }}</label>
        <input type="text" class="form-control" id="tax_reference" name="tax_reference"
               value="{{ old('tax_reference', $vendor->tax_reference ?? '') }}">
    </div>

    <div class="col-md-4">
        <label for="status" class="form-label">{{ __('vendor.status') }}</label>
        <select class="form-select" id="status" name="status" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $vendor->status ?? 'ACTIVE') === $status)>
                    {{ __('vendor.status_'.strtolower($status)) }}
                </option>
            @endforeach
        </select>
        {{-- Blacklisting is a decision about the vendor, not housekeeping;
             saying what it does prevents it being used as a delete button. --}}
        <div class="form-text">{{ __('vendor.blacklisted_hint') }}</div>
    </div>

    <div class="col-md-8">
        <label for="notes" class="form-label">{{ __('vendor.notes') }}</label>
        <textarea class="form-control" id="notes" name="notes" rows="2">{{ old('notes', $vendor->notes ?? '') }}</textarea>
    </div>
</div>
