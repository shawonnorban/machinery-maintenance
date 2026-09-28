@extends('layouts.app')
@section('title', $template->name)

@section('content')
    <x-page-header :title="$template->name" :subtitle="$template->code">
        <x-slot:actions>
            @if ($isOwn)
                @can('maintenance.template.update')
                    <a href="{{ route('app.maintenance.templates.edit', $template->id) }}"
                       class="btn btn-sm btn-outline-secondary">{{ __('common.edit') }}</a>

                    @if ($version?->status === 'PUBLISHED')
                        {{-- A published checklist is frozen. A revision is a new
                             draft that takes effect when it is published, so the
                             work orders that ran the old one keep resolving it. --}}
                        <form method="POST" action="{{ route('app.maintenance.templates.draft', $template->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-info">{{ __('maintenance.start_revision') }}</button>
                        </form>
                    @endif
                @endcan

                @can('maintenance.template.publish')
                    @if ($version?->status === 'DRAFT')
                        <form method="POST"
                              action="{{ route('app.maintenance.templates.publish', [$template->id, $version->id]) }}"
                              onsubmit="return confirm(@js(__('maintenance.publish_confirm')))">
                            @csrf
                            <button class="btn btn-sm btn-info text-white">{{ __('maintenance.publish') }}</button>
                        </form>
                    @endif
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

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
                                                    @if ($isOwn && $version?->status === 'DRAFT')
                                                        @can('maintenance.template.update')
                                                            <form method="POST"
                                                                  action="{{ route('app.maintenance.templates.items.destroy', [$template->id, $version->id, $item->id]) }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-sm btn-outline-danger btn-icon"
                                                                        title="{{ __('common.delete') }}"
                                                                        aria-label="{{ __('common.delete') }}">
                                                                    <i class="cil-trash" aria-hidden="true"></i>
                                                                </button>
                                                            </form>
                                                        @endcan
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

                @if ($isOwn && $version?->status === 'DRAFT')
                    @can('maintenance.template.update')
                        <div class="card-body border-top">
                            <form method="POST"
                                  action="{{ route('app.maintenance.templates.items.store', [$template->id, $version->id]) }}"
                                  class="row g-2 align-items-end">
                                @csrf

                                <div class="col-md-5">
                                    <label for="label" class="form-label mb-1">{{ __('maintenance.add_check') }}</label>
                                    <input id="label" name="label" type="text" class="form-control form-control-sm"
                                           value="{{ old('label') }}" required maxlength="500">
                                    @error('label')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-3">
                                    <label for="input_type" class="form-label mb-1">{{ __('maintenance.input_type') }}</label>
                                    <select id="input_type" name="input_type" class="form-select form-select-sm" required>
                                        @foreach ($inputTypes as $type)
                                            <option value="{{ $type }}" @selected(old('input_type') === $type)>
                                                {{ __('maintenance.input_'.strtolower($type)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label for="unit" class="form-label mb-1">{{ __('maintenance.unit') }}</label>
                                    <input id="unit" name="unit" type="text" class="form-control form-control-sm"
                                           maxlength="32" placeholder="mm, bar, °C">
                                </div>

                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-info text-white w-100">{{ __('common.save') }}</button>
                                </div>

                                <div class="col-md-3">
                                    <label for="tolerance_min" class="form-label mb-1">{{ __('maintenance.tolerance_min') }}</label>
                                    <input id="tolerance_min" name="tolerance_min" type="number" step="0.0001"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-3">
                                    <label for="tolerance_max" class="form-label mb-1">{{ __('maintenance.tolerance_max') }}</label>
                                    <input id="tolerance_max" name="tolerance_max" type="number" step="0.0001"
                                           class="form-control form-control-sm">
                                </div>

                                <div class="col-md-6 d-flex gap-3 align-items-end pb-1">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="required" name="required"
                                               value="1" checked>
                                        <label class="form-check-label small" for="required">
                                            {{ __('maintenance.required') }}
                                        </label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_safety_item"
                                               name="is_safety_item" value="1">
                                        <label class="form-check-label small" for="is_safety_item">
                                            {{ __('maintenance.safety') }}
                                        </label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    {{-- A safety check is not a stricter tick box: failing
                                         one demands a photo and a note, and raises
                                         corrective work by itself. --}}
                                    <div class="form-text">{{ __('maintenance.safety_hint') }}</div>
                                </div>
                            </form>
                        </div>
                    @endcan
                @endif
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
