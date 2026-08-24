@extends('platform::layout')
@section('title', __('platform.new_tenant'))

@section('content')
    <h1 class="h4 mb-1">{{ __('platform.new_tenant') }}</h1>
    <p class="text-body-secondary">{{ __('platform.new_tenant_intro') }}</p>

    <form method="POST" action="{{ route('platform.tenants.store') }}" class="row g-4">
        @csrf

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('platform.company') }}</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">{{ __('platform.company_name') }}</label>
                        <input id="name" name="name" type="text" required maxlength="255"
                               value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="code" class="form-label">{{ __('platform.company_code') }}</label>
                        <input id="code" name="code" type="text" required maxlength="32"
                               value="{{ old('code') }}"
                               class="form-control @error('code') is-invalid @enderror">
                        {{-- It ends up inside every work order and breakdown
                             number this customer will ever issue, so it is
                             worth a moment now. --}}
                        <div class="form-text">{{ __('platform.company_code_hint') }}</div>
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="legal_name" class="form-label">{{ __('platform.legal_name') }}</label>
                        <input id="legal_name" name="legal_name" type="text" maxlength="255"
                               value="{{ old('legal_name') }}" class="form-control">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="base_currency" class="form-label">{{ __('platform.currency') }}</label>
                            <input id="base_currency" name="base_currency" type="text" required maxlength="3"
                                   value="{{ old('base_currency', 'BDT') }}" class="form-control">
                        </div>

                        <div class="col-md-8">
                            <label for="timezone" class="form-label">{{ __('platform.timezone') }}</label>
                            <input id="timezone" name="timezone" type="text" required maxlength="64"
                                   value="{{ old('timezone', 'Asia/Dhaka') }}" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label for="default_locale" class="form-label">{{ __('platform.locale') }}</label>
                            <select id="default_locale" name="default_locale" class="form-select">
                                <option value="bn" @selected(old('default_locale', 'bn') === 'bn')>বাংলা</option>
                                <option value="en" @selected(old('default_locale') === 'en')>English</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">{{ __('platform.first_factory') }}</div>
                <div class="card-body">
                    {{-- Created with the company, because almost nothing works
                         without one: machines live in a factory, numbers are
                         issued per factory, the working calendar hangs off it. --}}
                    <div class="form-text mb-3">{{ __('platform.first_factory_hint') }}</div>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="factory_name" class="form-label">{{ __('platform.factory_name') }}</label>
                            <input id="factory_name" name="factory_name" type="text" required maxlength="255"
                                   value="{{ old('factory_name') }}"
                                   class="form-control @error('factory_name') is-invalid @enderror">
                            @error('factory_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="factory_code" class="form-label">{{ __('platform.factory_code') }}</label>
                            <input id="factory_code" name="factory_code" type="text" required maxlength="32"
                                   value="{{ old('factory_code') }}"
                                   class="form-control @error('factory_code') is-invalid @enderror">
                            @error('factory_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('platform.owner_account') }}</div>
                <div class="card-body">
                    <div class="form-text mb-3">{{ __('platform.owner_hint') }}</div>

                    <div class="mb-3">
                        <label for="owner_name" class="form-label">{{ __('platform.owner_name') }}</label>
                        <input id="owner_name" name="owner_name" type="text" required maxlength="255"
                               value="{{ old('owner_name') }}"
                               class="form-control @error('owner_name') is-invalid @enderror">
                        @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="owner_email" class="form-label">{{ __('platform.owner_email') }}</label>
                        <input id="owner_email" name="owner_email" type="email" required maxlength="255"
                               value="{{ old('owner_email') }}"
                               class="form-control @error('owner_email') is-invalid @enderror">
                        @error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="card-footer">
                    <button class="btn btn-primary">{{ __('platform.create_tenant') }}</button>
                    <a href="{{ route('platform.tenants') }}" class="btn btn-link">{{ __('common.cancel') }}</a>
                </div>
            </div>
        </div>
    </form>
@endsection
