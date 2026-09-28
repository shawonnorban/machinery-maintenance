@extends('layouts.app')
@section('title', __('asset.transfer_asset'))

@section('content')
    <x-page-header :title="__('asset.transfer_asset')" :subtitle="$asset->asset_code.' — '.$asset->name" />

    <div class="row">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('app.assets.transfer.store', $asset) }}">
                @csrf
                <input type="hidden" name="version" value="{{ $asset->version }}">

                <div class="card">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('asset.current_location') }}</label>
                            <p class="form-control-plaintext">
                                {{ $asset->factory?->name }} / {{ $asset->location?->full_path ?: $asset->location?->name }}
                            </p>
                        </div>

                        <div class="mb-3">
                            <label for="to_location_id" class="form-label">
                                {{ __('asset.destination') }} <span class="text-danger">*</span>
                            </label>
                            <select id="to_location_id" name="to_location_id" required
                                    class="form-select @error('to_location_id') is-invalid @enderror">
                                <option value="">&mdash;</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected(old('to_location_id') === $location->id)>
                                        {{ $location->factory?->name }} / {{ $location->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('to_location_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="reason" class="form-label">{{ __('asset.reason') }} <span class="text-danger">*</span></label>
                            <input id="reason" name="reason" type="text" maxlength="255" required
                                   class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}">
                            @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-0">
                            <label for="notes" class="form-label">{{ __('asset.notes') }}</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                    <div class="card-footer d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ __('asset.transfer') }}</button>
                        <a href="{{ route('app.assets.show', $asset) }}" class="btn btn-outline-secondary">{{ __('asset.clear') }}</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-body small text-body-secondary">
                    {{-- Stating the rule beats a user discovering it through a
                         validation error after filling the form. --}}
                    <p class="mb-2">{{ __('asset.factory_change_needs_transfer') }}</p>
                    <p class="mb-0">{{ __('asset.transfer_requested', ['number' => '…']) }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
