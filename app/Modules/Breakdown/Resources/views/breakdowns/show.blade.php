@extends('layouts.app')
@section('title', $breakdown->breakdown_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.breakdowns.index') }}">{{ __('breakdown.breakdowns') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $breakdown->breakdown_number }}</li>
@endsection

@section('content')
    <x-page-header :title="$breakdown->breakdown_number"
                   :subtitle="$breakdown->asset?->asset_code.' — '.$breakdown->asset?->name">
        <x-slot:actions>
            @include('work_order::work-orders._priority', ['priority' => $breakdown->priority])
            @include('breakdown::breakdowns._status', ['status' => $breakdown->status])
        </x-slot:actions>
    </x-page-header>

    @if ($originalBreakdown !== null)
        {{-- Stated plainly, because this record is excluded from every failure
             count and MTBF figure and somebody reading it needs to know why. --}}
        <div class="alert alert-info">
            <strong>{{ __('breakdown.recurrence_of', ['number' => $originalBreakdown->breakdown_number]) }}</strong>
            <div class="small">{{ __('breakdown.recurrence_note') }}</div>
            <a href="{{ route('app.breakdowns.show', $originalBreakdown) }}" class="small">
                {{ $originalBreakdown->breakdown_number }}
            </a>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            @include('breakdown::breakdowns._actions')
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-warning" aria-hidden="true"></i>
                    <span>{{ __('breakdown.details') }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('breakdown.asset') }}</dt>
                        <dd class="col-sm-8">
                            <a href="{{ route('app.assets.show', $breakdown->asset_id) }}">
                                {{ $breakdown->asset?->asset_code }}
                            </a>
                            — {{ $breakdown->asset?->name }}
                        </dd>

                        <dt class="col-sm-4">{{ __('asset.factory') }}</dt>
                        <dd class="col-sm-8">{{ $breakdown->factory?->name }}</dd>

                        @if ($breakdown->productionLine !== null)
                            <dt class="col-sm-4">{{ __('breakdown.production_line') }}</dt>
                            <dd class="col-sm-8">{{ $breakdown->productionLine->name }}</dd>
                        @endif

                        <dt class="col-sm-4">{{ __('breakdown.problem_description') }}</dt>
                        <dd class="col-sm-8" style="white-space: pre-line">{{ $breakdown->problem_description }}</dd>

                        <dt class="col-sm-4">{{ __('breakdown.severity') }}</dt>
                        <dd class="col-sm-8">{{ __('breakdown.severity_'.strtolower($breakdown->severity)) }}</dd>

                        <dt class="col-sm-4">{{ __('breakdown.technician') }}</dt>
                        <dd class="col-sm-8">{{ $breakdown->assignedTechnician?->name ?? '—' }}</dd>

                        <dt class="col-sm-4">{{ __('breakdown.failure_code') }}</dt>
                        <dd class="col-sm-8">{{ $breakdown->failureCode?->label() ?? '—' }}</dd>

                        <dt class="col-sm-4">{{ __('breakdown.root_cause') }}</dt>
                        <dd class="col-sm-8">{{ $breakdown->rootCause?->label() ?? '—' }}</dd>

                        @if ($breakdown->corrective_action)
                            <dt class="col-sm-4">{{ __('breakdown.corrective_action') }}</dt>
                            <dd class="col-sm-8" style="white-space: pre-line">{{ $breakdown->corrective_action }}</dd>
                        @endif

                        @if ($breakdown->preventive_action)
                            <dt class="col-sm-4">{{ __('breakdown.preventive_action') }}</dt>
                            <dd class="col-sm-8" style="white-space: pre-line">{{ $breakdown->preventive_action }}</dd>
                        @endif

                        @if ($breakdown->cancellation_reason)
                            <dt class="col-sm-4">{{ __('breakdown.cancellation_reason') }}</dt>
                            <dd class="col-sm-8">{{ $breakdown->cancellation_reason }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @include('breakdown::breakdowns._chain')

            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-task" aria-hidden="true"></i>
                    <span>{{ __('breakdown.work_orders') }}</span>
                </div>

                <div class="list-group list-group-flush">
                    @forelse ($workOrders as $workOrder)
                        <a class="list-group-item list-group-item-action d-flex align-items-center gap-2"
                           href="{{ route('app.work-orders.show', $workOrder) }}">
                            <span class="fw-semibold">{{ $workOrder->work_order_number }}</span>
                            <span class="text-body-secondary">{{ $workOrder->maintenanceType?->name }}</span>
                            <span class="ms-auto">
                                @include('work_order::work-orders._status', ['status' => $workOrder->status])
                            </span>
                        </a>
                    @empty
                        <div class="list-group-item text-body-secondary">{{ __('breakdown.no_work_orders') }}</div>
                    @endforelse
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-industry" aria-hidden="true"></i>
                    <span>{{ __('breakdown.production_impact') }}</span>
                </div>

                <div class="list-group list-group-flush">
                    @forelse ($breakdown->productionImpacts as $impact)
                        <div class="list-group-item">
                            <div class="d-flex gap-3">
                                <span>{{ $impact->productionLine?->name ?? '—' }}</span>
                                <span class="text-body-secondary">{{ $impact->production_order_reference }}</span>
                                <span class="ms-auto">
                                    {{ $impact->actual_loss ?? $impact->estimated_loss ?? '—' }}
                                    {{ __('breakdown.pieces') }}
                                </span>
                            </div>
                            @if ($impact->notes)
                                <div class="small text-body-secondary">{{ $impact->notes }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-body-secondary">{{ __('breakdown.no_impact_recorded') }}</div>
                    @endforelse
                </div>

                @can('breakdown.breakdown.repair')
                    @unless ($breakdown->isTerminal())
                        <div class="card-body border-top">
                            <form method="POST" action="{{ route('app.breakdowns.impact', $breakdown) }}"
                                  class="row g-2 align-items-end">
                                @csrf

                                <div class="col-md-4">
                                    <label for="impact_estimated_loss" class="form-label mb-1">
                                        {{ __('breakdown.estimated_loss') }}
                                    </label>
                                    <input id="impact_estimated_loss" name="estimated_loss" type="number"
                                           step="1" min="0" class="form-control form-control-sm">
                                </div>

                                <div class="col-md-4">
                                    <label for="impact_actual_loss" class="form-label mb-1">
                                        {{ __('breakdown.actual_loss') }}
                                    </label>
                                    <input id="impact_actual_loss" name="actual_loss" type="number"
                                           step="1" min="0" class="form-control form-control-sm">
                                </div>

                                <div class="col-md-4">
                                    <button class="btn btn-sm btn-info text-white w-100">
                                        {{ __('breakdown.record_impact') }}
                                    </button>
                                </div>

                                <div class="col-12">
                                    {{-- Pieces, not money: converting output to
                                         currency needs a costing rate this system
                                         does not hold, and a fabricated figure gets
                                         quoted in a meeting as fact. --}}
                                    <div class="form-text">{{ __('breakdown.impact_unit_note') }}</div>
                                </div>
                            </form>
                        </div>
                    @endunless
                @endcan
            </div>
        </div>

        <div class="col-lg-5">
            @include('breakdown::breakdowns._downtime')

            @if ($recurrences->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-loop" aria-hidden="true"></i>
                        <span>{{ __('breakdown.recurrences') }}</span>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($recurrences as $recurrence)
                            <a class="list-group-item list-group-item-action"
                               href="{{ route('app.breakdowns.show', $recurrence) }}">
                                {{ $recurrence->breakdown_number }}
                                <div class="small text-body-secondary">@dt($recurrence->reported_at)</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-history" aria-hidden="true"></i>
                    <span>{{ __('breakdown.timeline') }}</span>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($breakdown->statusHistories as $history)
                        <div class="list-group-item">
                            <div class="d-flex align-items-center gap-2">
                                @if ($history->from_status !== null && $history->from_status !== $history->to_status)
                                    <span class="text-body-secondary small">
                                        {{ __('breakdown.status_'.strtolower($history->from_status)) }} →
                                    </span>
                                @endif

                                @include('breakdown::breakdowns._status', ['status' => $history->to_status])

                                <span class="ms-auto text-body-secondary small">@dt($history->changed_at)</span>
                            </div>

                            @if ($history->reason)
                                <div class="small text-body-secondary mt-1">{{ $history->reason }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
