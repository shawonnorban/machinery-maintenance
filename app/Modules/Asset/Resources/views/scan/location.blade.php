@extends('layouts.mobile')
@section('title', $location->name)

@section('topbar')
    <span class="fw-semibold">{{ $location->name }}</span>
    <span class="ms-auto small text-body-secondary">{{ $location->factory?->name }}</span>
@endsection

@section('content')
    <div class="mb-3">
        <div class="text-body-secondary small">{{ $location->full_path ?: $location->name }}</div>
    </div>

    <h2 class="h6 text-body-secondary text-uppercase mb-3">
        {{ __('scan.assets_here') }} ({{ $assets->count() }})
    </h2>

    @if ($assets->isEmpty())
        {{-- Naming both possibilities matters: an auditor standing here needs
             to know whether to look harder or register something. --}}
        <x-empty-state :title="__('scan.no_assets_here')"
                       :description="__('scan.no_assets_here_hint')" />
    @else
        <div class="list-group">
            @foreach ($assets as $asset)
                <a href="{{ route('app.assets.show', $asset) }}"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                    <span>
                        <span class="fw-semibold d-block">{{ $asset->asset_code }}</span>
                        <span class="small text-body-secondary">{{ $asset->name }}</span>
                    </span>
                    @include('asset::assets._status', ['status' => $asset->status])
                </a>
            @endforeach
        </div>
    @endif
@endsection
