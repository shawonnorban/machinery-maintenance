@extends('layouts.app')
@section('title', __('asset.edit_asset'))

@section('content')
    <x-page-header :title="__('asset.edit_asset')" :subtitle="$asset->asset_code" />

    <form method="POST" action="{{ route('app.assets.update', $asset) }}">
        @csrf
        @method('PATCH')
        {{-- Optimistic locking: a stale version returns 409 with the current
             one, so the UI can offer a reload rather than a dead end. --}}
        <input type="hidden" name="version" value="{{ $asset->version }}">

        <div class="card mb-4">
            <div class="card-body">
                @include('asset::assets._form', ['asset' => $asset])
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('asset.edit_asset') }}</button>
                <a href="{{ route('app.assets.show', $asset) }}" class="btn btn-outline-secondary">{{ __('asset.clear') }}</a>
            </div>
        </div>
    </form>
@endsection
