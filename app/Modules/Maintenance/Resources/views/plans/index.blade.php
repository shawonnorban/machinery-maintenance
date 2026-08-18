@extends('layouts.app')
@section('title', __('maintenance.plans'))

@section('content')
    <x-page-header :title="__('maintenance.plans')">
        <x-slot:actions>
            @can('maintenance.plan.create')
                <a href="{{ route('app.maintenance.plans.create') }}" class="btn btn-primary">
                    {{ __('maintenance.new_plan') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-body p-0">
            @if ($plans->isEmpty())
                <x-empty-state :title="__('maintenance.no_plans')" :description="__('maintenance.no_plans_hint')">
                    <x-slot:action>
                        @can('maintenance.plan.create')
                            <a href="{{ route('app.maintenance.plans.create') }}" class="btn btn-primary">
                                {{ __('maintenance.new_plan') }}
                            </a>
                        @endcan
                    </x-slot:action>
                </x-empty-state>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('maintenance.plan') }}</th>
                                <th>{{ __('maintenance.target') }}</th>
                                <th>{{ __('maintenance.trigger') }}</th>
                                <th>{{ __('maintenance.next_due') }}</th>
                                <th>{{ __('maintenance.open_occurrences') }}</th>
                                <th>{{ __('maintenance.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plans as $plan)
                                <tr>
                                    <td>
                                        <a href="{{ route('app.maintenance.plans.show', $plan) }}"
                                           class="fw-semibold text-decoration-none">{{ $plan->name }}</a>
                                    </td>
                                    <td class="small">
                                        {{ $plan->asset?->asset_code ?? $plan->assetType?->name ?? '—' }}
                                    </td>
                                    <td class="small">
                                        {{ __('maintenance.trigger_'.strtolower($plan->trigger_type)) }}
                                        @if ($plan->trigger_type === 'COMBINED')
                                            <span class="text-body-secondary">
                                                ({{ __('maintenance.logic_'.strtolower($plan->rule_logic ?? 'or')) }})
                                            </span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $plan->next_due_at?->toDateString() ?? '—' }}</td>
                                    <td class="small">{{ $plan->open_schedules_count }}</td>
                                    <td>
                                        <x-status-pill :status="$plan->active ? 'ACTIVE' : 'INACTIVE'"
                                                       :tone="$plan->active ? 'success' : 'secondary'">
                                            {{ $plan->active ? __('maintenance.active') : __('maintenance.inactive') }}
                                        </x-status-pill>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($plans->hasPages())
            <div class="card-footer">{{ $plans->links() }}</div>
        @endif
    </div>
@endsection
