@extends('layouts.app')
@section('title', __('approval.approvals'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('approval.approvals') }}</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-6">
            {{-- What is waiting on the person reading the screen, not what is
                 waiting in general. The second number is somebody else's
                 problem and putting it first makes the queue ignorable. --}}
            <x-kpi-tile :label="__('approval.pending_for_me')"
                        :value="number_format($counts['mine'])" tone="warning" />
        </div>
        <div class="col-sm-6">
            <x-kpi-tile :label="__('approval.all_pending')"
                        :value="number_format($counts['pending'])" tone="secondary" />
        </div>
    </div>

    <ul class="nav nav-pills mb-3">
        @foreach (['PENDING' => 'status_pending', 'APPROVED' => 'status_approved', 'REJECTED' => 'status_rejected', 'ALL' => 'all_pending'] as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $status === $key ? 'active' : '' }}"
                   href="{{ route('app.approvals', ['status' => $key]) }}">
                    {{ __('approval.'.$label) }}
                </a>
            </li>
        @endforeach
    </ul>

    <form method="GET" action="{{ route('app.approvals') }}" id="list-filter">
        <input type="hidden" name="status" value="{{ $status }}">

        <x-data-table :title="__('approval.approvals')" icon="cil-check-circle"
                      :paginator="$requests" :searchable="false">
            <thead>
                <tr>
                    <th class="col-index">{{ __('common.row_number') }}</th>
                    <th>{{ __('approval.entity') }}</th>
                    <th>{{ __('approval.cost') }}</th>
                    <th>{{ __('approval.step') }}</th>
                    <th>{{ __('approval.requested_at') }}</th>
                    <th>{{ __('approval.status') }}</th>
                    <th>{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $index => $request)
                    @php
                        $workOrder = $workOrders[$request->entity_id] ?? null;
                        $canAct = in_array($request->id, $actionable, true);
                    @endphp

                    <tr class="{{ $canAct ? 'table-warning' : '' }}">
                        <td class="col-index">{{ $requests->firstItem() + $index }}</td>
                        <td>
                            @if ($workOrder !== null)
                                <a href="{{ route('app.work-orders.show', $workOrder) }}" class="fw-semibold">
                                    {{ $workOrder->work_order_number }}
                                </a>
                                <div class="text-body-secondary">
                                    {{ $workOrder->asset?->asset_code }} — {{ Str::limit($workOrder->title, 32) }}
                                </div>
                            @else
                                <span class="text-body-secondary">{{ $request->entity_type }}</span>
                            @endif
                        </td>
                        <td>
                            {{-- The frozen figure, not today's estimate. --}}
                            {{ number_format((float) ($request->context_json['cost'] ?? 0), 2) }}
                            {{ $request->context_json['currency'] ?? '' }}
                        </td>
                        <td>
                            {{ $request->current_step }}
                            <span class="text-body-secondary">
                                {{ __('approval.of_steps', ['total' => $request->total_steps]) }}
                            </span>
                        </td>
                        <td>@dt($request->requested_at)</td>
                        <td>
                            @php
                                $tone = match ($request->status) {
                                    'PENDING' => 'warning',
                                    'APPROVED' => 'success',
                                    'REJECTED', 'EXPIRED' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <x-status-pill :status="$request->status" :tone="$tone">
                                {{ __('approval.status_'.strtolower($request->status)) }}
                            </x-status-pill>
                        </td>
                        <td>
                            <a href="{{ route('app.approvals.show', $request) }}"
                               class="btn btn-sm {{ $canAct ? 'btn-warning' : 'btn-info text-white' }} btn-icon"
                               title="{{ __('common.view') }}" aria-label="{{ __('common.view') }}">
                                <i class="cil-magnifying-glass" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-0">
                        <x-empty-state :title="__('approval.no_requests')"
                                       :description="__('approval.no_requests_hint')" />
                    </td></tr>
                @endforelse
            </tbody>
        </x-data-table>
    </form>
@endsection
