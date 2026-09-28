@extends('layouts.app')
@section('title', $factory ? $factory->name : __('settings.new_factory'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.settings.factories') }}">{{ __('settings.factories') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ $factory ? $factory->name : __('settings.new_factory') }}
    </li>
@endsection

@section('content')
    <x-page-header :title="$factory ? __('settings.edit_factory') : __('settings.new_factory')">
        <x-slot:actions>
            <a href="{{ route('app.settings.factories') }}" class="btn btn-sm btn-outline-secondary">
                <i class="cil-arrow-left" aria-hidden="true"></i> {{ __('common.back') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST"
          action="{{ $factory
              ? route('app.settings.factories.update', $factory)
              : route('app.settings.factories.store') }}">
        @csrf
        @if ($factory)
            @method('PATCH')
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label">{{ __('settings.factory_name') }}</label>
                        <input id="name" name="name" type="text" class="form-control"
                               value="{{ old('name', $factory?->name) }}" required maxlength="255">
                        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="code" class="form-label">{{ __('settings.code') }}</label>

                        @if ($factory)
                            {{-- Fixed after creation: it is printed on labels and
                                 embedded in work order numbers, so changing it would
                                 strand everything already issued. --}}
                            <input id="code" type="text" class="form-control" value="{{ $factory->code }}" disabled>
                            <div class="form-text">{{ __('settings.code_is_permanent') }}</div>
                        @else
                            <input id="code" name="code" type="text" class="form-control text-uppercase"
                                   value="{{ old('code') }}" required maxlength="5">
                            <div class="form-text">{{ __('settings.code_hint') }}</div>
                        @endif

                        @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="timezone" class="form-label">{{ __('settings.timezone') }}</label>
                        <select id="timezone" name="timezone" class="form-select" required>
                            @foreach ($timezones as $zone)
                                <option value="{{ $zone }}"
                                        @selected(old('timezone', $factory?->timezone ?? 'Asia/Dhaka') === $zone)>
                                    {{ $zone }}
                                </option>
                            @endforeach
                        </select>
                        {{-- Downtime and response times are measured on this clock. --}}
                        <div class="form-text">{{ __('settings.timezone_hint') }}</div>
                        @error('timezone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label">{{ __('settings.address') }}</label>
                        <textarea id="address" name="address" class="form-control" rows="2"
                                  maxlength="2000">{{ old('address', $factory?->address) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-info text-white">{{ __('common.save') }}</button>
            <a href="{{ route('app.settings.factories') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
