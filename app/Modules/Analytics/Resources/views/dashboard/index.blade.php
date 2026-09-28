@extends('layouts.app')
@section('title', __('dashboard.dashboard'))

@section('content')
    <x-page-header :title="__('dashboard.dashboard')" :subtitle="$company?->name">
        <x-slot:actions>
            {{-- Period is a page control; the factory scope is global and lives
                 in the header, so it is not repeated here (Frontend 4.2). --}}
            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('dashboard.period') }}">
                @foreach ($periods as $option)
                    <a class="btn {{ $days === $option ? 'btn-info text-white' : 'btn-outline-secondary' }}"
                       href="{{ route('app.dashboard', ['days' => $option]) }}">
                        {{ __('dashboard.last_days', ['days' => $option]) }}
                    </a>
                @endforeach
            </div>
        </x-slot:actions>
    </x-page-header>

    @if (! $canSeeManagement && ! $canSeeMaintenance && ! $canSeeStore)
        {{-- Said plainly rather than showing an empty page. A technician's work
             is in the lists, not on a dashboard. --}}
        <x-empty-state :title="__('dashboard.no_panels')" :description="__('dashboard.no_panels_hint')" />
    @endif

    @if ($canSeeManagement)
        @include('analytics::dashboard._management', ['data' => $management])
    @endif

    @if ($canSeeMaintenance)
        @include('analytics::dashboard._maintenance', ['data' => $maintenance])
    @endif

    @if ($canSeeStore)
        @include('analytics::dashboard._store', ['data' => $store])
    @endif

    <p class="small text-body-secondary">{{ __('dashboard.period_note') }}</p>
@endsection
