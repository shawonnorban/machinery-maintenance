@extends('layouts.app')
@section('title', __('asset.new_asset'))

@section('content')
    <x-page-header :title="__('asset.new_asset')" />

    <form method="POST" action="{{ route('app.assets.store') }}">
        @csrf

        <div class="card mb-4">
            <div class="card-body">
                @include('asset::assets._form')
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('asset.new_asset') }}</button>
                <a href="{{ route('app.assets.index') }}" class="btn btn-outline-secondary">{{ __('asset.clear') }}</a>
            </div>
        </div>
    </form>
@endsection
