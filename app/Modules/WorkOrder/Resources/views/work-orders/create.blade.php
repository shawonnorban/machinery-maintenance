@extends('layouts.app')
@section('title', __('work_order.new_work_order'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.work-orders.index') }}">{{ __('work_order.work_orders') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('work_order.new_work_order') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('work_order.new_work_order')" />

    <form method="POST" action="{{ route('app.work-orders.store') }}">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-task" aria-hidden="true"></i>
                        <span>{{ __('work_order.details') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="asset_id" class="form-label">{{ __('work_order.asset') }}</label>
                            <select id="asset_id" name="asset_id" class="form-select" required data-tom-select>
                                <option value="">—</option>
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->id }}" @selected(old('asset_id') === $asset->id)>
                                        {{ $asset->asset_code }} — {{ $asset->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('asset_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">{{ __('maintenance.name') }}</label>
                            <input id="title" name="title" type="text" class="form-control"
                                   value="{{ old('title') }}" required maxlength="255">
                            @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">{{ __('work_order.description') }}</label>
                            <textarea id="description" name="description" class="form-control" rows="3"
                                      maxlength="5000">{{ old('description') }}</textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="maintenance_type_id" class="form-label">{{ __('work_order.maintenance_type') }}</label>
                                <select id="maintenance_type_id" name="maintenance_type_id" class="form-select" required>
                                    <option value="">—</option>
                                    @foreach ($maintenanceTypes as $type)
                                        <option value="{{ $type->id }}" @selected(old('maintenance_type_id') === $type->id)>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('maintenance_type_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="priority" class="form-label">{{ __('work_order.priority') }}</label>
                                <select id="priority" name="priority" class="form-select" required>
                                    @foreach (['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'] as $option)
                                        <option value="{{ $option }}" @selected(old('priority', 'MEDIUM') === $option)>
                                            {{ __('work_order.priority_'.strtolower($option)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-list-rich" aria-hidden="true"></i>
                        <span>{{ __('work_order.checklist') }}</span>
                    </div>
                    <div class="card-body">
                        <label for="template_version_id" class="form-label">{{ __('maintenance.templates') }}</label>
                        <select id="template_version_id" name="template_version_id" class="form-select">
                            <option value="">—</option>
                            @foreach ($templates as $template)
                                @php $current = $template->currentVersion(); @endphp
                                <option value="{{ $current->id }}" @selected(old('template_version_id') === $current->id)>
                                    {{ $template->name }} (v{{ $current->version_number }})
                                </option>
                            @endforeach
                        </select>
                        {{-- The version is frozen onto the work order at creation, so a
                             template edited next month does not change what this
                             technician was asked to check. --}}
                        <div class="form-text">{{ __('work_order.checklist_version_hint') }}</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-calendar" aria-hidden="true"></i>
                        <span>{{ __('work_order.schedule') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="scheduled_start" class="form-label">{{ __('work_order.scheduled_start') }}</label>
                            <input id="scheduled_start" name="scheduled_start" type="datetime-local"
                                   class="form-control" value="{{ old('scheduled_start') }}">
                        </div>

                        <div class="mb-3">
                            <label for="scheduled_end" class="form-label">{{ __('work_order.scheduled_end') }}</label>
                            <input id="scheduled_end" name="scheduled_end" type="datetime-local"
                                   class="form-control" value="{{ old('scheduled_end') }}">
                            @error('scheduled_end')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="assigned_team_id" class="form-label">{{ __('nav.teams') }}</label>
                            <select id="assigned_team_id" name="assigned_team_id" class="form-select">
                                <option value="">—</option>
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}" @selected(old('assigned_team_id') === $team->id)>
                                        {{ $team->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-check">
                            <input id="requires_shutdown" name="requires_shutdown" type="checkbox" value="1"
                                   class="form-check-input" @checked(old('requires_shutdown'))>
                            <label for="requires_shutdown" class="form-check-label">
                                {{ __('work_order.requires_shutdown') }}
                            </label>
                            {{-- A shutdown job stops the machine and its stoppage counts
                                 as planned downtime, so it is stated up front (ADR-049). --}}
                            <div class="form-text">{{ __('work_order.shutdown_hint') }}</div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-calculator" aria-hidden="true"></i>
                        <span>{{ __('work_order.estimated') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="estimated_parts_cost" class="form-label">{{ __('work_order.parts') }}</label>
                            <input id="estimated_parts_cost" name="estimated_parts_cost" type="number" step="0.0001"
                                   min="0" class="form-control" value="{{ old('estimated_parts_cost') }}">
                        </div>

                        {{-- An estimate only. Actual cost is derived from the
                             part records underneath and is never typed in (ADR-064). --}}
                        <div class="form-text">{{ __('work_order.parts_pending_note') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-info text-white">{{ __('work_order.new_work_order') }}</button>
            <a href="{{ route('app.work-orders.index') }}" class="btn btn-outline-secondary">{{ __('asset.clear') }}</a>
        </div>
    </form>
@endsection
