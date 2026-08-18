@extends('layouts.app')
@section('title', $template->name)

@section('content')
    <x-page-header :title="$template->name" :subtitle="$template->code" />

    <div class="row">
        <div class="col-lg-9">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    {{ __('maintenance.items') }}
                    @if ($version)
                        <span class="ms-auto d-flex align-items-center gap-2">
                            <span class="small text-body-secondary">
                                {{ __('maintenance.version') }} {{ $version->version_number }}
                            </span>
                            <x-status-pill :status="$version->status"
                                :tone="match ($version->status) {
                                    'PUBLISHED' => 'success',
                                    'DRAFT' => 'warning',
                                    default => 'secondary',
                                }">
                                {{ __('maintenance.status_'.strtolower($version->status)) }}
                            </x-status-pill>
                        </span>
                    @endif
                </div>

                <div class="card-body p-0">
                    @if ($items->isEmpty())
                        <x-empty-state :title="__('maintenance.no_items')" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 3rem">{{ __('maintenance.sequence') }}</th>
                                        <th>{{ __('maintenance.label') }}</th>
                                        <th>{{ __('maintenance.input_type') }}</th>
                                        <th>{{ __('maintenance.tolerance') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $item)
                                        <tr>
                                            <td class="text-body-secondary">{{ $item->sequence }}</td>
                                            <td>
                                                {{ $item->label }}
                                                @if ($item->help_text)
                                                    <div class="small text-body-secondary">{{ $item->help_text }}</div>
                                                @endif
                                            </td>
                                            <td class="small">
                                                {{ __('maintenance.input_'.strtolower($item->input_type)) }}
                                                @if ($item->unit)
                                                    <span class="text-body-secondary">({{ $item->unit }})</span>
                                                @endif
                                            </td>
                                            <td class="small text-body-secondary">
                                                @if ($item->hasTolerance())
                                                    {{ $item->tolerance_min ?? '−∞' }} … {{ $item->tolerance_max ?? '∞' }}
                                                @else
                                                    &mdash;
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex gap-1 justify-content-end">
                                                    @if ($item->required)
                                                        <x-status-pill status="REQUIRED" tone="info">
                                                            {{ __('maintenance.required') }}
                                                        </x-status-pill>
                                                    @endif
                                                    @if ($item->is_safety_item)
                                                        {{-- A failed safety item demands a note and a
                                                             photo, and raises corrective work. --}}
                                                        <x-status-pill status="SAFETY" tone="danger">
                                                            {{ __('maintenance.safety') }}
                                                        </x-status-pill>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card mb-4">
                <div class="card-header">{{ __('maintenance.versions') }}</div>
                <div class="list-group list-group-flush">
                    @foreach ($template->versions as $candidate)
                        <a href="{{ route('app.maintenance.templates.version', [$template->id, $candidate->id]) }}"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                                  {{ $version && $candidate->id === $version->id ? 'active' : '' }}">
                            <span>{{ __('maintenance.version') }} {{ $candidate->version_number }}</span>
                            <span class="small">{{ __('maintenance.status_'.strtolower($candidate->status)) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-6">{{ __('maintenance.asset_type') }}</dt>
                        <dd class="col-6">{{ $template->assetType?->name ?? '—' }}</dd>

                        <dt class="col-6">{{ __('maintenance.maintenance_type') }}</dt>
                        <dd class="col-6">{{ $template->maintenanceType?->name ?? '—' }}</dd>

                        @if ($version?->estimated_duration_minutes)
                            <dt class="col-6">{{ __('maintenance.duration') }}</dt>
                            <dd class="col-6">
                                {{ __('maintenance.minutes', ['count' => $version->estimated_duration_minutes]) }}
                            </dd>
                        @endif

                        @if ($version?->published_at)
                            <dt class="col-6">{{ __('maintenance.published_at') }}</dt>
                            <dd class="col-6">{{ $version->published_at->toDateString() }}</dd>
                        @endif
                    </dl>

                    @unless ($template->isEditable())
                        <hr>
                        <p class="mb-0 text-body-secondary">{{ __('maintenance.seeded_hint') }}</p>
                    @endunless
                </div>
            </div>
        </div>
    </div>
@endsection
