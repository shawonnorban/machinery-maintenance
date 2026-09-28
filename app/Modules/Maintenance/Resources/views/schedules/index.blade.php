@extends('layouts.app')
@section('title', __('maintenance.schedules'))

@section('content')
    <x-page-header :title="__('maintenance.schedules')" />

    <div class="row mb-4">
        <div class="col-sm-4">
            <x-kpi-tile :label="__('maintenance.filter_due')" :value="number_format($counts['due'])" tone="warning" />
        </div>
        <div class="col-sm-4">
            {{-- Overdue counts only what is past its grace period. A plan with
                 a two-day grace is not late on day one (SRS 31.1). --}}
            <x-kpi-tile :label="__('maintenance.filter_overdue')" :value="number_format($counts['overdue'])" tone="danger" />
        </div>
        <div class="col-sm-4">
            <x-kpi-tile :label="__('maintenance.filter_planned')" :value="number_format($counts['planned'])" tone="secondary" />
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-pills card-header-pills">
                @foreach (['' => 'filter_all', 'DUE' => 'filter_due', 'OVERDUE' => 'filter_overdue', 'PLANNED' => 'filter_planned'] as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ (string) $status === (string) $key ? 'active' : '' }}"
                           href="{{ route('app.maintenance.schedule', $key === '' ? [] : ['status' => $key]) }}">
                            {{ __('maintenance.'.$label) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body p-0">
            @if ($schedules->isEmpty())
                <x-empty-state :title="__('maintenance.no_schedules')"
                               :description="__('maintenance.no_schedules_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="col-index">{{ __('common.row_number') }}</th>
                                <th>{{ __('maintenance.due_at') }}</th>
                                <th>{{ __('asset.asset') }}</th>
                                <th>{{ __('maintenance.plan') }}</th>
                                <th>{{ __('maintenance.triggered_by') }}</th>
                                <th>{{ __('maintenance.status') }}</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedules as $index => $schedule)
                                <tr>
                                    <td class="col-index">{{ $schedules->firstItem() + $index }}</td>
                                    <td>
                                        {{ $schedule->due_at->toDateString() }}
                                        @if ($schedule->isOverdue())
                                            <div class="small text-danger">
                                                {{ __('maintenance.status_overdue') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td >
                                        <a href="{{ route('app.assets.show', $schedule->asset_id) }}">
                                            {{ $schedule->asset?->asset_code }}
                                        </a>
                                        <div class="text-body-secondary">{{ $schedule->asset?->name }}</div>
                                    </td>
                                    <td >
                                        <a href="{{ route('app.maintenance.plans.show', $schedule->maintenance_plan_id) }}">
                                            {{ $schedule->plan?->name }}
                                        </a>
                                    </td>
                                    <td class="small text-body-secondary">{{ $schedule->triggered_by ?? '—' }}</td>
                                    <td>@include('maintenance::plans._status', ['status' => $schedule->status])</td>
                                    <td class="text-end">
                                        @if ($schedule->isOpen())
                                            <div class="d-flex gap-1 justify-content-end">
                                                <form method="POST" action="{{ route('app.maintenance.schedules.complete', $schedule) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-success">
                                                        {{ __('maintenance.complete') }}
                                                    </button>
                                                </form>

                                                @can('maintenance.schedule.skip')
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                            data-coreui-toggle="modal"
                                                            data-coreui-target="#skip-{{ $schedule->id }}">
                                                        {{ __('maintenance.skip') }}
                                                    </button>
                                                @endcan
                                            </div>
                                        @endif
                                    </td>
                                </tr>

                                @can('maintenance.schedule.skip')
                                    @if ($schedule->isOpen())
                                        <tr class="d-none"><td colspan="7">
                                            <div class="modal fade" id="skip-{{ $schedule->id }}" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <form class="modal-content" method="POST"
                                                          action="{{ route('app.maintenance.schedules.skip', $schedule) }}">
                                                        @csrf
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">{{ __('maintenance.skip') }}</h5>
                                                        </div>
                                                        <div class="modal-body">
                                                            <label for="reason-{{ $schedule->id }}" class="form-label">
                                                                {{ __('maintenance.skip_reason') }}
                                                            </label>
                                                            <input id="reason-{{ $schedule->id }}" name="skipped_reason"
                                                                   type="text" class="form-control" required maxlength="255">
                                                            {{-- A skipped service is a compliance exception, so it is
                                                                 never anonymous. --}}
                                                            <div class="form-text">{{ __('maintenance.skip_needs_reason') }}</div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary"
                                                                    data-coreui-dismiss="modal">{{ __('asset.clear') }}</button>
                                                            <button type="submit" class="btn btn-primary">
                                                                {{ __('maintenance.skip') }}
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </td></tr>
                                    @endif
                                @endcan
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($schedules->hasPages())
            <div class="table-footer">
                <div>{{ __('common.showing_entries', ['from' => $schedules->firstItem(), 'to' => $schedules->lastItem(), 'total' => number_format($schedules->total())]) }}</div>
                <div class="ms-auto">{{ $schedules->onEachSide(1)->links() }}</div>
            </div>
        @endif
    </div>
@endsection
