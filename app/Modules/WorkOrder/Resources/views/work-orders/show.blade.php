@extends('layouts.app')
@section('title', $workOrder->work_order_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.work-orders.index') }}">{{ __('work_order.work_orders') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $workOrder->work_order_number }}</li>
@endsection

@section('content')
    @php
        // A technician may only answer the checklist while the job is running.
        $canExecute = $workOrder->status === 'IN_PROGRESS'
            && auth()->user()->can('work_order.work_order.start');
    @endphp

    <x-page-header :title="$workOrder->work_order_number" :subtitle="$workOrder->title">
        <x-slot:actions>
            @include('work_order::work-orders._priority', ['priority' => $workOrder->priority])
            @include('work_order::work-orders._status', ['status' => $workOrder->status])
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-3">
        <div class="card-body">
            @include('work_order::work-orders._actions')
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-task" aria-hidden="true"></i>
                    <span>{{ __('work_order.details') }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('work_order.asset') }}</dt>
                        <dd class="col-sm-8">
                            <a href="{{ route('app.assets.show', $workOrder->asset_id) }}">
                                {{ $workOrder->asset?->asset_code }}
                            </a>
                            — {{ $workOrder->asset?->name }}
                        </dd>

                        <dt class="col-sm-4">{{ __('asset.factory') }}</dt>
                        <dd class="col-sm-8">{{ $workOrder->factory?->name }}</dd>

                        <dt class="col-sm-4">{{ __('work_order.maintenance_type') }}</dt>
                        <dd class="col-sm-8">{{ $workOrder->maintenanceType?->name }}</dd>

                        <dt class="col-sm-4">{{ __('work_order.source') }}</dt>
                        <dd class="col-sm-8">{{ __('work_order.source_'.strtolower($workOrder->source)) }}</dd>

                        @if ($workOrder->description)
                            <dt class="col-sm-4">{{ __('work_order.description') }}</dt>
                            <dd class="col-sm-8" style="white-space: pre-line">{{ $workOrder->description }}</dd>
                        @endif

                        <dt class="col-sm-4">{{ __('work_order.scheduled_start') }}</dt>
                        <dd class="col-sm-8">{{ $workOrder->scheduled_start?->format('Y-m-d H:i') ?? '—' }}</dd>

                        <dt class="col-sm-4">{{ __('work_order.actual_start') }}</dt>
                        <dd class="col-sm-8">{{ $workOrder->actual_start?->format('Y-m-d H:i') ?? '—' }}</dd>

                        <dt class="col-sm-4">{{ __('work_order.actual_end') }}</dt>
                        <dd class="col-sm-8">{{ $workOrder->actual_end?->format('Y-m-d H:i') ?? '—' }}</dd>

                        @if ($workOrder->hold_minutes > 0)
                            <dt class="col-sm-4">{{ __('work_order.hold_time') }}</dt>
                            <dd class="col-sm-8">{{ number_format($workOrder->hold_minutes) }} {{ __('work_order.minutes') }}</dd>
                        @endif

                        @if ($workOrder->repairMinutes() !== null)
                            <dt class="col-sm-4">{{ __('work_order.repair_time') }}</dt>
                            <dd class="col-sm-8">
                                {{ number_format($workOrder->repairMinutes()) }} {{ __('work_order.minutes') }}
                                {{-- Hold time excluded (ADR-051). --}}
                                <div class="text-body-secondary small">{{ __('work_order.repair_time_note') }}</div>
                            </dd>
                        @endif

                        @if ($workOrder->requires_shutdown)
                            <dt class="col-sm-4">{{ __('work_order.requires_shutdown') }}</dt>
                            <dd class="col-sm-8">{{ __('common.yes') }}</dd>
                        @endif

                        @if ($workOrder->requires_verification)
                            <dt class="col-sm-4">{{ __('work_order.requires_verification') }}</dt>
                            <dd class="col-sm-8">{{ __('common.yes') }}</dd>
                        @endif

                        @if ($workOrder->reopened_count > 0)
                            <dt class="col-sm-4">{{ __('work_order.reopened_count') }}</dt>
                            <dd class="col-sm-8">{{ $workOrder->reopened_count }}×</dd>
                        @endif

                        @if ($workOrder->cancellation_reason)
                            <dt class="col-sm-4">{{ __('work_order.cancellation_reason') }}</dt>
                            <dd class="col-sm-8">{{ $workOrder->cancellation_reason }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @if ($items->isNotEmpty())
                @include('work_order::work-orders._checklist')
            @endif

            @include('work_order::work-orders._labor')
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-people" aria-hidden="true"></i>
                    <span>{{ __('work_order.assigned_to') }}</span>
                </div>

                <div class="list-group list-group-flush">
                    @forelse ($workOrder->activeAssignments as $assignment)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <div>
                                <div>{{ $assignment->technician?->name }}</div>
                                <div class="text-body-secondary small">{{ $assignment->technician?->employee_id }}</div>
                            </div>

                            @can('work_order.work_order.assign')
                                @unless ($workOrder->isTerminal())
                                    <form method="POST" action="{{ route('app.work-orders.unassign', $workOrder) }}"
                                          class="ms-auto">
                                        @csrf
                                        <input type="hidden" name="technician_id" value="{{ $assignment->technician_id }}">
                                        <button class="btn btn-sm btn-outline-secondary">{{ __('work_order.unassign') }}</button>
                                    </form>
                                @endunless
                            @endcan
                        </div>
                    @empty
                        <div class="list-group-item text-body-secondary">{{ __('work_order.nobody_assigned') }}</div>
                    @endforelse
                </div>

                @can('work_order.work_order.assign')
                    @unless ($workOrder->isTerminal())
                        <div class="card-body border-top">
                            <form method="POST" action="{{ route('app.work-orders.assign', $workOrder) }}">
                                @csrf
                                <label for="technician_ids" class="form-label">{{ __('work_order.technicians') }}</label>
                                <select id="technician_ids" name="technician_ids[]" class="form-select" multiple
                                        size="5" required data-tom-select>
                                    @foreach ($technicians as $technician)
                                        <option value="{{ $technician->id }}"
                                            @selected($workOrder->activeAssignments->contains('technician_id', $technician->id))>
                                            {{ $technician->name }} ({{ $technician->employee_id }})
                                        </option>
                                    @endforeach
                                </select>
                                {{-- Only technicians at this factory are listed: sending
                                     someone to another site is a transfer, not an
                                     assignment. --}}
                                <button class="btn btn-sm btn-info text-white mt-2">{{ __('work_order.assign') }}</button>
                            </form>
                        </div>
                    @endunless
                @endcan
            </div>

            @if ($showCosts)
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-calculator" aria-hidden="true"></i>
                        <span>{{ __('work_order.cost') }}</span>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td>{{ __('work_order.labor') }}</td>
                                    <td class="text-end">{{ $workOrder->actual_labor_cost }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('work_order.parts') }}</td>
                                    <td class="text-end">{{ $workOrder->actual_parts_cost }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('work_order.other') }}</td>
                                    <td class="text-end">{{ $workOrder->actual_other_cost }}</td>
                                </tr>
                                <tr class="fw-semibold border-top">
                                    <td>{{ __('work_order.total') }}</td>
                                    <td class="text-end">{{ $workOrder->actual_cost }} {{ $workOrder->currency }}</td>
                                </tr>
                                @if ($workOrder->estimated_labor_cost !== null || $workOrder->estimated_parts_cost !== null)
                                    <tr class="text-body-secondary">
                                        <td>{{ __('work_order.estimated') }}</td>
                                        <td class="text-end">
                                            {{ bcadd(
                                                (string) ($workOrder->estimated_labor_cost ?? '0'),
                                                (string) ($workOrder->estimated_parts_cost ?? '0'),
                                                4,
                                            ) }}
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>

                        {{-- Derived from the labour and part records underneath, never
                             typed in: a total that disagrees with its own lines is
                             worse than no total (ADR-064). --}}
                        <div class="form-text">{{ __('work_order.parts_pending_note') }}</div>
                    </div>
                </div>
            @endif

            @if ($attachments->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-paperclip" aria-hidden="true"></i>
                        <span>{{ __('work_order.attachments') }}</span>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($attachments as $attachment)
                            <a class="list-group-item list-group-item-action"
                               href="{{ route('app.attachments.show', $attachment) }}"
                               target="_blank" rel="noopener">
                                {{ $attachment->original_name }}
                                <div class="text-body-secondary small">
                                    {{ $attachment->humanSize() }} · {{ $attachment->created_at->format('Y-m-d H:i') }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @include('work_order::work-orders._timeline')
        </div>
    </div>
@endsection
