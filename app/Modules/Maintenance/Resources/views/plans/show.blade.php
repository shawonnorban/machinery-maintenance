@extends('layouts.app')
@section('title', $plan->name)

@section('content')
    <x-page-header :title="$plan->name"
                   :subtitle="$plan->asset?->asset_code ?? $plan->assetType?->name">
        <x-slot:actions>
            @can('maintenance.plan.update')
                <a href="{{ route('app.maintenance.plans.edit', $plan) }}" class="btn btn-outline-secondary">
                    {{ __('maintenance.edit_plan') }}
                </a>
            @endcan

            @can('maintenance.plan.activate')
                @if ($plan->active)
                    <form method="POST" action="{{ route('app.maintenance.plans.deactivate', $plan) }}">
                        @csrf
                        <button class="btn btn-outline-warning">{{ __('maintenance.deactivate') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('app.maintenance.plans.activate', $plan) }}">
                        @csrf
                        <button class="btn btn-primary">{{ __('maintenance.activate') }}</button>
                    </form>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    {{ __('maintenance.occurrences') }}
                    <span class="ms-auto">
                        <x-status-pill :status="$plan->active ? 'ACTIVE' : 'INACTIVE'"
                                       :tone="$plan->active ? 'success' : 'secondary'">
                            {{ $plan->active ? __('maintenance.active') : __('maintenance.inactive') }}
                        </x-status-pill>
                    </span>
                </div>
                <div class="card-body p-0">
                    @if ($schedules->isEmpty())
                        <x-empty-state :title="__('maintenance.no_schedules')"
                                       :description="__('maintenance.no_schedules_hint')" />
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('maintenance.due_at') }}</th>
                                        <th>{{ __('asset.asset') }}</th>
                                        <th>{{ __('maintenance.due_meter') }}</th>
                                        <th>{{ __('maintenance.triggered_by') }}</th>
                                        <th>{{ __('maintenance.status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($schedules as $schedule)
                                        <tr>
                                            <td class="small">{{ $schedule->due_at->toDateString() }}</td>
                                            <td class="small">{{ $schedule->asset?->asset_code }}</td>
                                            <td class="small text-body-secondary">{{ $schedule->due_meter ?? '—' }}</td>
                                            <td class="small text-body-secondary">{{ $schedule->triggered_by ?? '—' }}</td>
                                            <td>@include('maintenance::plans._status', ['status' => $schedule->status])</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-6">{{ __('maintenance.trigger') }}</dt>
                        <dd class="col-6">{{ __('maintenance.trigger_'.strtolower($plan->trigger_type)) }}</dd>

                        @if ($plan->trigger_type === 'COMBINED')
                            <dt class="col-6">{{ __('maintenance.rule_logic') }}</dt>
                            <dd class="col-6">{{ __('maintenance.logic_'.strtolower($plan->rule_logic ?? 'or')) }}</dd>
                        @endif

                        <dt class="col-6">{{ __('maintenance.schedule_mode') }}</dt>
                        <dd class="col-6">{{ __('maintenance.mode_'.strtolower($plan->schedule_mode)) }}</dd>

                        @foreach ($plan->rules as $rule)
                            <dt class="col-6">
                                {{ $rule->rule_type === 'TIME' ? __('maintenance.interval') : __('maintenance.meter_threshold') }}
                            </dt>
                            <dd class="col-6">
                                {{ __('maintenance.every') }} {{ (int) (float) $rule->value }} {{ $rule->unit }}
                            </dd>
                        @endforeach

                        <dt class="col-6">{{ __('maintenance.non_working_day') }}</dt>
                        <dd class="col-6">
                            {{ __('maintenance.policy_'.match ($plan->non_working_day_policy) {
                                'NEXT_WORKING_DAY' => 'next',
                                'PREVIOUS_WORKING_DAY' => 'previous',
                                default => 'none',
                            }) }}
                        </dd>

                        <dt class="col-6">{{ __('maintenance.grace') }}</dt>
                        <dd class="col-6">{{ $plan->grace_period_minutes }}</dd>

                        <dt class="col-6">{{ __('maintenance.lead_time') }}</dt>
                        <dd class="col-6">{{ $plan->lead_time_days }}</dd>

                        <dt class="col-6">{{ __('maintenance.next_due') }}</dt>
                        <dd class="col-6">{{ $plan->next_due_at?->toDateString() ?? '—' }}</dd>

                        @if ($plan->templateVersion)
                            <dt class="col-6">{{ __('maintenance.template') }}</dt>
                            <dd class="col-6">v{{ $plan->templateVersion->version_number }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
