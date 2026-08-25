@extends('platform::layout')
@section('title', __('platform.edit_company', ['name' => $company->name]))

@section('content')
    <div class="mb-2">
        <a href="{{ route('platform.tenants.show', $company) }}" class="small text-decoration-none">
            ← {{ $company->name }}
        </a>
    </div>

    <x-page-header :title="__('platform.edit_company', ['name' => $company->name])"
                   :subtitle="__('platform.edit_company_hint')" />

    <div class="platform-panels">
        {{-- The logo on its own form. A file input cannot share a form with
             plain fields without turning every text save into a multipart
             upload, so it gets its own button rather than borrowing the
             one below. --}}
        <section class="panel">
            <header class="panel-head">
                <i class="cil-image" aria-hidden="true"></i>
                <span>{{ __('platform.logo') }}</span>
            </header>

            <div class="panel-body">
                <div class="d-flex align-items-start gap-3">
                    <div class="tenant-logo tenant-logo-lg">
                        @if ($company->logoUrl())
                            <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}">
                        @else
                            <span class="tenant-logo-empty">{{ __('platform.no_logo') }}</span>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('platform.tenants.logo', $company) }}"
                          enctype="multipart/form-data" class="flex-grow-1">
                        @csrf
                        @error('logo')<div class="alert alert-danger py-2 mb-2">{{ $message }}</div>@enderror

                        <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                               class="form-control form-control-sm" required>
                        <div class="form-text">{{ __('platform.logo_hint') }}</div>
                        <button class="btn btn-primary mt-2">{{ __('platform.logo_save') }}</button>
                    </form>
                </div>
            </div>
        </section>

        <section class="panel">
            <header class="panel-head">
                <i class="cil-building" aria-hidden="true"></i>
                <span>{{ __('platform.details') }}</span>
            </header>

            <form method="POST" action="{{ route('platform.tenants.details', $company) }}">
                @csrf
                @method('PATCH')

                <div class="panel-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="detail_name" class="form-label">{{ __('platform.company_name') }}</label>
                            <input id="detail_name" name="name" type="text" required maxlength="255"
                                   value="{{ old('name', $company->name) }}"
                                   class="form-control form-control-sm @error('name') is-invalid @enderror">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label for="detail_legal" class="form-label">{{ __('platform.legal_name') }}</label>
                            <input id="detail_legal" name="legal_name" type="text" maxlength="255"
                                   value="{{ old('legal_name', $company->legal_name) }}"
                                   class="form-control form-control-sm">
                        </div>

                        <div class="col-6">
                            <label for="detail_email" class="form-label">{{ __('platform.company_email') }}</label>
                            <input id="detail_email" name="email" type="email" maxlength="255"
                                   value="{{ old('email', $company->email) }}"
                                   class="form-control form-control-sm @error('email') is-invalid @enderror">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-6">
                            <label for="detail_phone" class="form-label">{{ __('platform.company_phone') }}</label>
                            <input id="detail_phone" name="phone" type="text" maxlength="32"
                                   value="{{ old('phone', $company->phone) }}" class="form-control form-control-sm">
                        </div>

                        <div class="col-6">
                            <label for="detail_country" class="form-label">{{ __('platform.company_country') }}</label>
                            <input id="detail_country" name="country" type="text" maxlength="100"
                                   value="{{ old('country', $company->country) }}"
                                   class="form-control form-control-sm">
                        </div>

                        <div class="col-6">
                            <label for="detail_currency" class="form-label">{{ __('platform.currency') }}</label>
                            <input id="detail_currency" name="base_currency" type="text" required maxlength="3"
                                   value="{{ old('base_currency', $company->base_currency) }}"
                                   class="form-control form-control-sm">
                        </div>

                        <div class="col-12">
                            <label for="detail_address" class="form-label">{{ __('platform.company_address') }}</label>
                            <textarea id="detail_address" name="address" rows="2" maxlength="500"
                                      class="form-control form-control-sm">{{ old('address', $company->address) }}</textarea>
                        </div>

                        <div class="col-6">
                            <label for="detail_locale" class="form-label">{{ __('platform.locale') }}</label>
                            <select id="detail_locale" name="default_locale" class="form-select form-select-sm">
                                <option value="bn" @selected(old('default_locale', $company->default_locale) === 'bn')>বাংলা</option>
                                <option value="en" @selected(old('default_locale', $company->default_locale) === 'en')>English</option>
                            </select>
                        </div>

                        <div class="col-6">
                            <label for="detail_tz" class="form-label">{{ __('platform.timezone') }}</label>
                            <input id="detail_tz" name="timezone" type="text" required maxlength="64"
                                   value="{{ old('timezone', $company->timezone) }}"
                                   class="form-control form-control-sm">
                        </div>
                    </div>

                    {{-- The code is not editable, and says why. It is inside every
                         work order and breakdown number this customer has ever
                         issued, and changing it would leave documents naming a
                         code that no longer exists. --}}
                    <div class="form-text mt-3">{{ __('platform.code_is_fixed', ['code' => $company->code]) }}</div>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary">{{ __('common.save') }}</button>
                        <a href="{{ route('platform.tenants.show', $company) }}"
                           class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
                    </div>
                </div>
            </form>
        </section>
    </div>
@endsection
