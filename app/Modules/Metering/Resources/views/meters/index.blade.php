@extends('layouts.app')
@section('title', __('metering.meters'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('metering.meters') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('metering.meters')" :subtitle="__('metering.intro')" />

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('asset.asset') }}</th>
                        <th>{{ __('metering.meter') }}</th>
                        <th class="text-end">{{ __('metering.current_value') }}</th>
                        <th>{{ __('metering.last_reading') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($meters as $meter)
                        @php($isStale = $meter->last_reading_at === null || $meter->last_reading_at->lessThan($stale))

                        <tr class="{{ $isStale ? 'table-warning' : '' }}">
                            <td>
                                <a href="{{ route('app.assets.show', $meter->asset_id) }}">
                                    {{ $meter->asset?->asset_code }}
                                </a>
                                <div class="small text-body-secondary">{{ $meter->asset?->name }}</div>
                            </td>

                            <td>
                                {{ $meter->type?->name }}
                                @unless ($meter->type?->is_cumulative)
                                    {{-- A non-cumulative meter may legitimately go
                                         down, so the rule that refuses a lower
                                         reading does not apply to it. --}}
                                    <span class="badge bg-light text-dark">{{ __('metering.non_cumulative') }}</span>
                                @endunless
                            </td>

                            <td class="text-end">
                                {{ $meter->current_value }} {{ $meter->type?->unit }}
                            </td>

                            <td>
                                @if ($meter->last_reading_at)
                                    <span @class(['text-danger fw-semibold' => $isStale])>
                                        @dt($meter->last_reading_at)
                                    </span>
                                    @if ($isStale)
                                        {{-- A meter nobody has touched for a fortnight
                                             is the one quietly making a usage-based
                                             plan wrong. --}}
                                        <div class="small">{{ __('metering.stale') }}</div>
                                    @endif
                                @else
                                    <span class="text-body-secondary">{{ __('metering.never_read') }}</span>
                                @endif
                            </td>

                            <td class="text-end">
                                <a href="{{ route('app.meters.show', $meter) }}"
                                   class="btn btn-sm btn-info text-white">
                                    {{ __('metering.record_reading') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state :title="__('metering.no_meters')"
                                               :description="__('metering.no_meters_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($meters->hasPages())
            <div class="card-footer">{{ $meters->links() }}</div>
        @endif
    </div>
@endsection
