<h2 class="h6 text-body-secondary text-uppercase mb-2">{{ __('dashboard.maintenance_dashboard') }}</h2>

<div class="row">
    <div class="col-sm-6 col-xl-3">
        <x-kpi-tile :label="__('dashboard.todays_tasks')"
                    :value="number_format($data['today'])" tone="primary" />
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-kpi-tile :label="__('dashboard.overdue_maintenance')"
                    :value="number_format($data['overdue'])"
                    :caption="__('dashboard.overdue_hint')"
                    :tone="$data['overdue'] > 0 ? 'danger' : 'success'" />
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-kpi-tile :label="__('dashboard.open_work_orders')"
                    :value="number_format($data['open_work_orders'])" tone="info" />
    </div>

    <div class="col-sm-6 col-xl-3">
        {{-- Unacknowledged, not merely active: a machine down that nobody has
             picked up is the number worth watching. --}}
        <x-kpi-tile :label="__('dashboard.unacknowledged')"
                    :value="number_format($data['unacknowledged_breakdowns'])"
                    :caption="__('dashboard.unacknowledged_hint')"
                    :tone="$data['unacknowledged_breakdowns'] > 0 ? 'danger' : 'success'" />
    </div>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">
                <i class="cil-calendar" aria-hidden="true"></i>
                <span>{{ __('dashboard.pm_compliance') }}</span>
            </div>
            <div class="card-body">
                <x-kpi-tile :label="__('dashboard.pm_compliance')"
                            :value="$data['pm_compliance_percent'] === null ? null : $data['pm_compliance_percent'].'%'"
                            :caption="__('dashboard.pm_compliance_hint')"
                            :reason="__('dashboard.not_available_reason')"
                            :tone="($data['pm_compliance_percent'] ?? 100) >= 90 ? 'success' : 'warning'" />

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td>{{ __('dashboard.due') }}</td>
                            <td class="text-end">{{ number_format($data['due']) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('dashboard.active_breakdowns') }}</td>
                            <td class="text-end">{{ number_format($data['active_breakdowns']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header">
                <i class="cil-people" aria-hidden="true"></i>
                <span>{{ __('dashboard.technician_workload') }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('dashboard.technician') }}</th>
                            <th class="text-end">{{ __('dashboard.open_jobs') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['workload'] as $row)
                            <tr class="{{ $row->at_capacity ? 'table-warning' : '' }}">
                                <td>
                                    {{ $row->technician->name }}
                                    <div class="text-body-secondary">{{ $row->technician->employee_id }}</div>
                                </td>
                                <td class="text-end fw-semibold">{{ $row->open_count }}</td>
                                <td class="text-end">
                                    @if ($row->at_capacity)
                                        {{-- Shown so a queue of twenty against one
                                             person is visible as the planning
                                             fiction it is. --}}
                                        <x-status-pill status="FULL" tone="warning">
                                            {{ __('dashboard.at_capacity') }}
                                        </x-status-pill>
                                    @elseif ($row->technician->max_concurrent_work_orders !== null)
                                        <span class="small text-body-secondary">
                                            / {{ $row->technician->max_concurrent_work_orders }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-body-secondary">
                                    {{ __('dashboard.no_technicians') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
