@extends('layouts.app')
@section('title', __('work_order.work_orders'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('work_order.work_orders') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-3">
            <x-kpi-tile :label="__('work_order.filter_open')" :value="number_format($counts['open'])" tone="primary" />
        </div>
        <div class="col-sm-3">
            <x-kpi-tile :label="__('work_order.filter_in_progress')" :value="number_format($counts['in_progress'])" tone="info" />
        </div>
        <div class="col-sm-3">
            {{-- Held work is the number worth watching: it is work that looks
                 scheduled while nothing is happening to it. --}}
            <x-kpi-tile :label="__('work_order.filter_on_hold')" :value="number_format($counts['on_hold'])" tone="warning" />
        </div>
        <div class="col-sm-3">
            <x-kpi-tile :label="__('work_order.filter_awaiting_verification')"
                        :value="number_format($counts['awaiting_verification'])" tone="secondary" />
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach (['' => 'filter_open', 'IN_PROGRESS' => 'filter_in_progress', 'ON_HOLD' => 'filter_on_hold', 'COMPLETED' => 'status_completed', 'ALL' => 'filter_all'] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ (string) $status === (string) $key ? 'active' : '' }}"
                   href="{{ route('app.work-orders.index', $key === '' ? [] : ['status' => $key]) }}">
                    {{ __('work_order.'.$label) }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- One form drives search, per-page and the filters, so changing any of
         them keeps the others. --}}
    <form method="GET" action="{{ route('app.work-orders.index') }}" id="list-filter">
        <input type="hidden" name="status" value="{{ $status }}">

        <x-data-table :title="__('work_order.work_orders')" icon="cil-task" :paginator="$workOrders">
            <x-slot:actions>
                @can('work_order.work_order.create')
                    <a href="{{ route('app.work-orders.create') }}" class="btn btn-sm btn-info text-white">
                        <i class="cil-plus" aria-hidden="true"></i> {{ __('common.add_new') }}
                    </a>
                @endcan
            </x-slot:actions>

            <x-slot:toolbar>
                <select name="priority" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                    <option value="">{{ __('work_order.priority') }}: {{ __('common.all') }}</option>
                    @foreach (['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'] as $option)
                        <option value="{{ $option }}" @selected(request('priority') === $option)>
                            {{ __('work_order.priority_'.strtolower($option)) }}
                        </option>
                    @endforeach
                </select>
            </x-slot:toolbar>

            <thead>
                <tr>
                    <th class="col-index">{{ __('common.row_number') }}</th>
                    <th>{{ __('work_order.work_order') }}</th>
                    <th>{{ __('work_order.asset') }}</th>
                    <th>{{ __('work_order.maintenance_type') }}</th>
                    <th>{{ __('work_order.assigned_to') }}</th>
                    <th>{{ __('work_order.due') }}</th>
                    <th>{{ __('work_order.priority') }}</th>
                    <th>{{ __('work_order.status') }}</th>
                    <th>{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workOrders as $index => $workOrder)
                    <tr>
                        <td class="col-index">{{ $workOrders->firstItem() + $index }}</td>
                        <td>
                            <a href="{{ route('app.work-orders.show', $workOrder) }}" class="fw-semibold">
                                {{ $workOrder->work_order_number }}
                            </a>
                            <div class="text-body-secondary">{{ Str::limit($workOrder->title, 48) }}</div>
                        </td>
                        <td>
                            {{ $workOrder->asset?->asset_code }}
                            <div class="text-body-secondary">{{ Str::limit($workOrder->asset?->name ?? '', 28) }}</div>
                        </td>
                        <td>{{ $workOrder->maintenanceType?->name }}</td>
                        <td>
                            @forelse ($workOrder->activeAssignments as $assignment)
                                <div>{{ $assignment->technician?->name }}</div>
                            @empty
                                <span class="text-body-secondary">—</span>
                            @endforelse
                        </td>
                        <td>
                            {{ $workOrder->scheduled_start?->toDateString() ?? '—' }}
                            @if ($workOrder->scheduled_start !== null
                                 && $workOrder->scheduled_start->isPast()
                                 && $workOrder->isOpen())
                                <div class="small text-danger">{{ __('maintenance.status_overdue') }}</div>
                            @endif
                        </td>
                        <td>@include('work_order::work-orders._priority', ['priority' => $workOrder->priority])</td>
                        <td>@include('work_order::work-orders._status', ['status' => $workOrder->status])</td>
                        <td>
                            <a href="{{ route('app.work-orders.show', $workOrder) }}"
                               class="btn btn-sm btn-info text-white btn-icon"
                               title="{{ __('common.view') }}" aria-label="{{ __('common.view') }}">
                                <i class="cil-eye" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-0">
                        <x-empty-state :title="__('work_order.no_work_orders')"
                                       :description="__('work_order.no_work_orders_hint')">
                            <x-slot:action>
                                @can('work_order.work_order.create')
                                    <a href="{{ route('app.work-orders.create') }}" class="btn btn-sm btn-info text-white">
                                        {{ __('work_order.new_work_order') }}
                                    </a>
                                @endcan
                            </x-slot:action>
                        </x-empty-state>
                    </td></tr>
                @endforelse
            </tbody>
        </x-data-table>
    </form>
@endsection
