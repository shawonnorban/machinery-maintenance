@extends('layouts.app')
@section('title', __('maintenance.plans'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item">{{ __('nav.maintenance') }}</li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('maintenance.plans') }}</li>
@endsection

@section('content')
    <form method="GET" action="{{ route('app.maintenance.plans') }}" id="list-filter"></form>

    <x-data-table :title="__('maintenance.plans')" icon="cil-calendar" :paginator="$plans">
        <x-slot:actions>
            @can('maintenance.plan.create')
                <a href="{{ route('app.maintenance.plans.create') }}" class="btn btn-sm btn-info text-white">
                    <i class="cil-plus" aria-hidden="true"></i> {{ __('common.add_new') }}
                </a>
            @endcan
        </x-slot:actions>

        <thead>
            <tr>
                <th class="col-index">{{ __('common.row_number') }}</th>
                <th>{{ __('maintenance.plan') }}</th>
                <th>{{ __('maintenance.target') }}</th>
                <th>{{ __('maintenance.trigger') }}</th>
                <th>{{ __('maintenance.next_due') }}</th>
                <th>{{ __('maintenance.open_occurrences') }}</th>
                <th>{{ __('maintenance.status') }}</th>
                <th>{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($plans as $index => $plan)
                <tr>
                    <td class="col-index">{{ $plans->firstItem() + $index }}</td>
                    <td class="fw-semibold">{{ $plan->name }}</td>
                    <td>{{ $plan->asset?->asset_code ?? $plan->assetType?->name ?? '—' }}</td>
                    <td>
                        {{ __('maintenance.trigger_'.strtolower($plan->trigger_type)) }}
                        @if ($plan->trigger_type === 'COMBINED')
                            <span class="text-body-secondary">
                                ({{ __('maintenance.logic_'.strtolower($plan->rule_logic ?? 'or')) }})
                            </span>
                        @endif
                    </td>
                    <td>{{ $plan->next_due_at?->toDateString() ?? '—' }}</td>
                    <td>{{ $plan->open_schedules_count }}</td>
                    <td>
                        <x-status-pill :status="$plan->active ? 'ACTIVE' : 'INACTIVE'"
                                       :tone="$plan->active ? 'success' : 'secondary'">
                            {{ $plan->active ? __('maintenance.active') : __('maintenance.inactive') }}
                        </x-status-pill>
                    </td>
                    <td>
                        <a href="{{ route('app.maintenance.plans.show', $plan) }}"
                           class="btn btn-sm btn-info text-white btn-icon"
                           title="{{ __('common.view') }}" aria-label="{{ __('common.view') }}">
                            <i class="cil-eye" aria-hidden="true"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="p-0">
                    <x-empty-state :title="__('maintenance.no_plans')" :description="__('maintenance.no_plans_hint')">
                        <x-slot:action>
                            @can('maintenance.plan.create')
                                <a href="{{ route('app.maintenance.plans.create') }}" class="btn btn-sm btn-info text-white">
                                    {{ __('maintenance.new_plan') }}
                                </a>
                            @endcan
                        </x-slot:action>
                    </x-empty-state>
                </td></tr>
            @endforelse
        </tbody>
    </x-data-table>
@endsection
