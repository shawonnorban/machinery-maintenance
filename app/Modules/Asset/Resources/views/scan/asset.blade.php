{{-- The scan landing (Frontend 4.9). Uses the mobile shell: whoever reached
     this page did so by pointing a phone at a machine. --}}
@extends('layouts.mobile')
@section('title', $asset->asset_code)

@section('topbar')
    <span class="fw-semibold">{{ $asset->asset_code }}</span>
    <span class="ms-auto">
        @include('asset::assets._status', ['status' => $asset->status])
    </span>
@endsection

@section('content')
    <div class="mb-4">
        <h1 class="h5 mb-1">{{ $asset->name }}</h1>
        <div class="text-body-secondary small">
            {{ $asset->type?->name }}
            @if ($asset->location)
                &middot; {{ $asset->location->full_path ?: $asset->location->name }}
            @endif
        </div>
        <div class="mt-2">
            @include('asset::assets._criticality', ['criticality' => $asset->criticality])
        </div>
    </div>

    <h2 class="h6 text-body-secondary text-uppercase mb-3">
        {{ __('scan.what_would_you_like_to_do') }}
    </h2>

    @if ($actions === [])
        <x-empty-state :title="__('scan.no_actions')" />
    @else
        <div class="d-grid gap-3">
            @foreach ($actions as $action)
                @if ($action['route'] !== '')
                    <a href="{{ $action['route'] }}" class="btn btn-{{ $action['tone'] }} btn-lg text-start">
                        {{ $action['label'] }}
                    </a>
                @else
                    {{-- The module has not been built yet. A disabled control
                         naming the reason beats a link that would 404
                         (Frontend 9.1). --}}
                    <button type="button" class="btn btn-outline-{{ $action['tone'] }} btn-lg text-start" disabled>
                        {{ $action['label'] }}
                        <span class="d-block small opacity-75">{{ __('scan.not_yet_available') }}</span>
                    </button>
                @endif
            @endforeach
        </div>
    @endif
@endsection
