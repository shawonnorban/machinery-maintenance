@php
    $k = $data['kpis'];

    // Minutes are how the figures are stored; hours are how a manager thinks
    // about a month of downtime. Formatted here rather than in the calculator,
    // which stays in one unit so nothing has to guess.
    $asHours = fn (?int $minutes) => $minutes === null
        ? null
        : number_format($minutes / 60, 1).' '.__('dashboard.hours');
@endphp

<h2 class="h6 text-body-secondary text-uppercase mb-2">{{ __('dashboard.management') }}</h2>

<div class="row">
    <div class="col-sm-6 col-xl-3">
        {{-- Every tile renders N/A with a reason rather than 0. A manager
             acting on a fabricated zero is worse off than one who can see the
             figure is not available (SRS 31.2 rule 2). --}}
        <x-kpi-tile :label="__('dashboard.availability')"
                    :value="$k['availability_percent'] === null ? null : $k['availability_percent'].'%'"
                    :caption="__('dashboard.availability_hint')"
                    :reason="__('dashboard.not_available_reason')"
                    :tone="($k['availability_percent'] ?? 100) >= 90 ? 'success' : 'warning'" />
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-kpi-tile :label="__('dashboard.mtbf')"
                    :value="$asHours($k['mtbf_minutes'] === null ? null : (int) $k['mtbf_minutes'])"
                    :caption="__('dashboard.mtbf_hint')"
                    :reason="__('dashboard.not_available_reason')"
                    tone="info" />
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-kpi-tile :label="__('dashboard.mttr')"
                    :value="$k['mttr_minutes'] === null ? null : number_format($k['mttr_minutes'], 0).' '.__('dashboard.minutes')"
                    :caption="__('dashboard.mttr_hint')"
                    :reason="__('dashboard.not_available_reason')"
                    tone="primary" />
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-kpi-tile :label="__('dashboard.mtta')"
                    :value="$k['mtta_minutes'] === null ? null : number_format($k['mtta_minutes'], 0).' '.__('dashboard.minutes')"
                    :caption="__('dashboard.mtta_hint')"
                    :reason="__('dashboard.not_available_reason')"
                    tone="secondary" />
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header">
                <i class="cil-speedometer" aria-hidden="true"></i>
                <span>{{ __('dashboard.total_assets') }}</span>
                <span class="ms-auto fw-semibold">{{ number_format($data['assets']['total']) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        @foreach ([
                            'running' => 'success',
                            'idle' => 'secondary',
                            'breakdown' => 'danger',
                            'under_maintenance' => 'warning',
                            'under_repair' => 'info',
                        ] as $status => $tone)
                            <tr>
                                <td>
                                    <x-status-pill :status="$status" :tone="$tone">
                                        {{ __('dashboard.'.$status) }}
                                    </x-status-pill>
                                </td>
                                <td class="text-end fw-semibold">
                                    {{ number_format($data['assets'][$status] ?? 0) }}
                                </td>
                            </tr>
                        @endforeach

                        <tr class="border-top">
                            <td>
                                {{ __('dashboard.overdue_maintenance') }}
                                <div class="small text-body-secondary">{{ __('dashboard.overdue_hint') }}</div>
                            </td>
                            <td class="text-end fw-semibold {{ $data['overdue_maintenance'] > 0 ? 'text-danger' : '' }}">
                                {{ number_format($data['overdue_maintenance']) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">
                <i class="cil-money" aria-hidden="true"></i>
                <span>{{ __('dashboard.cost') }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <tr>
                            <td>{{ __('dashboard.maintenance_cost') }}</td>
                            <td class="text-end">{{ number_format((float) $data['cost']['maintenance'], 0) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('dashboard.breakdown_cost') }}</td>
                            <td class="text-end text-danger">
                                {{ number_format((float) $data['cost']['breakdown'], 0) }}
                            </td>
                        </tr>
                        <tr class="fw-semibold border-top">
                            <td>{{ __('dashboard.total_cost') }}</td>
                            <td class="text-end">{{ number_format((float) $data['cost']['total'], 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer small text-body-secondary">{{ __('dashboard.cost_hint') }}</div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <i class="cil-chart" aria-hidden="true"></i>
                <span>{{ __('dashboard.downtime_minutes') }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                        <tr>
                            <td>{{ __('dashboard.scheduled_minutes') }}</td>
                            <td class="text-end">{{ $asHours($k['scheduled_operating_minutes']) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('dashboard.operating_minutes') }}</td>
                            <td class="text-end">{{ $asHours($k['operating_minutes']) }}</td>
                        </tr>
                        <tr>
                            <td>{{ __('dashboard.unplanned_downtime') }}</td>
                            <td class="text-end text-danger">{{ $asHours($k['unplanned_downtime_minutes']) }}</td>
                        </tr>
                        <tr class="border-top">
                            <td>
                                {{ __('dashboard.failures') }}
                                <div class="small text-body-secondary">{{ __('dashboard.failures_hint') }}</div>
                            </td>
                            <td class="text-end fw-semibold">{{ number_format($k['failure_count']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
