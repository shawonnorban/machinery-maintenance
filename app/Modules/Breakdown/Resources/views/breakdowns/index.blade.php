@extends('layouts.app')
@section('title', __('breakdown.breakdowns'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('breakdown.breakdowns') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-3">
            <x-kpi-tile :label="__('breakdown.filter_open')" :value="number_format($counts['open'])" tone="danger" />
        </div>
        <div class="col-sm-3">
            {{-- The number a maintenance manager should watch: a machine is
                 down and nobody has picked it up yet. --}}
            <x-kpi-tile :label="__('breakdown.filter_unacknowledged')"
                        :value="number_format($counts['unacknowledged'])" tone="warning" />
        </div>
        <div class="col-sm-3">
            <x-kpi-tile :label="__('breakdown.filter_in_repair')" :value="number_format($counts['in_repair'])" tone="primary" />
        </div>
        <div class="col-sm-3">
            <x-kpi-tile :label="__('breakdown.filter_awaiting_closure')"
                        :value="number_format($counts['awaiting_closure'])" tone="info" />
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach ([
            '' => 'filter_open',
            'REPORTED' => 'filter_unacknowledged',
            'IN_REPAIR' => 'filter_in_repair',
            'REPAIRED' => 'filter_awaiting_closure',
            'ALL' => 'filter_all',
        ] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ (string) $status === (string) $key ? 'active' : '' }}"
                   href="{{ route('app.breakdowns.index', $key === '' ? [] : ['status' => $key]) }}">
                    {{ __('breakdown.'.$label) }}
                </a>
            </li>
        @endforeach
    </ul>

    <form method="GET" action="{{ route('app.breakdowns.index') }}" id="list-filter">
        <input type="hidden" name="status" value="{{ $status }}">

        <x-data-table :title="__('breakdown.breakdowns')" icon="cil-warning" :paginator="$breakdowns">
            <x-slot:actions>
                @can('breakdown.breakdown.create')
                    <a href="{{ route('app.breakdowns.create') }}" class="btn btn-sm btn-danger">
                        <i class="cil-plus" aria-hidden="true"></i> {{ __('breakdown.report_breakdown') }}
                    </a>
                @endcan
            </x-slot:actions>

            <x-slot:toolbar>
                <select name="priority" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
                    <option value="">{{ __('breakdown.priority') }}: {{ __('common.all') }}</option>
                    @foreach (['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'] as $option)
                        <option value="{{ $option }}" @selected(request('priority') === $option)>
                            {{ __('breakdown.priority_'.strtolower($option)) }}
                        </option>
                    @endforeach
                </select>
            </x-slot:toolbar>

            <thead>
                <tr>
                    <th class="col-index">{{ __('common.row_number') }}</th>
                    <th>{{ __('breakdown.breakdown_number') }}</th>
                    <th>{{ __('breakdown.asset') }}</th>
                    <th>{{ __('breakdown.failure_at') }}</th>
                    <th>{{ __('breakdown.down_for') }}</th>
                    <th>{{ __('breakdown.technician') }}</th>
                    <th>{{ __('breakdown.priority') }}</th>
                    <th>{{ __('breakdown.status') }}</th>
                    <th>{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($breakdowns as $index => $breakdown)
                    <tr>
                        <td class="col-index">{{ $breakdowns->firstItem() + $index }}</td>
                        <td>
                            <a href="{{ route('app.breakdowns.show', $breakdown) }}" class="fw-semibold">
                                {{ $breakdown->breakdown_number }}
                            </a>
                            @if ($breakdown->is_recurrence_of_breakdown_id !== null)
                                {{-- Flagged, because it is not counted as an
                                     independent failure in any report. --}}
                                <div class="small text-body-secondary">{{ __('breakdown.recurrences') }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $breakdown->asset?->asset_code }}
                            <div class="text-body-secondary">{{ Str::limit($breakdown->problem_description, 34) }}</div>
                        </td>
                        <td>@dt($breakdown->failure_at)</td>
                        <td>
                            @if ($breakdown->isOpen())
                                {{-- Elapsed time here on purpose: an operator asking
                                     "how long has this been down" means since when,
                                     not working minutes. The calendar-aware figure is
                                     on the detail screen, where it is labelled with
                                     the basis that produced it. --}}
                                <span class="text-danger">
                                    {{ $breakdown->failure_at->diffForHumans(null, true) }}
                                </span>
                            @else
                                @php $minutes = $breakdown->currentDowntime()?->total_downtime_minutes; @endphp
                                <span class="text-body-secondary">
                                    {{ $minutes === null ? '—' : number_format($minutes).' '.__('breakdown.minutes') }}
                                </span>
                            @endif
                        </td>
                        <td>{{ $breakdown->assignedTechnician?->name ?? '—' }}</td>
                        <td>@include('work_order::work-orders._priority', ['priority' => $breakdown->priority])</td>
                        <td>@include('breakdown::breakdowns._status', ['status' => $breakdown->status])</td>
                        <td>
                            <a href="{{ route('app.breakdowns.show', $breakdown) }}"
                               class="btn btn-sm btn-info text-white btn-icon"
                               title="{{ __('common.view') }}" aria-label="{{ __('common.view') }}">
                                <i class="cil-magnifying-glass" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-0">
                        <x-empty-state :title="__('breakdown.no_breakdowns')"
                                       :description="__('breakdown.no_breakdowns_hint')">
                            <x-slot:action>
                                @can('breakdown.breakdown.create')
                                    <a href="{{ route('app.breakdowns.create') }}" class="btn btn-sm btn-danger">
                                        {{ __('breakdown.report_breakdown') }}
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
