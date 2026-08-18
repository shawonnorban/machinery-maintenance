@extends('layouts.mobile')
@section('title', __('work_order.my_work'))

@section('topbar')
    <span class="fw-semibold">{{ __('work_order.my_work') }}</span>
    @if ($technician !== null)
        <span class="ms-auto small text-body-secondary">{{ $technician->name }}</span>
    @endif
@endsection

@section('content')
    @if ($technician === null)
        {{-- Not an error. A manager or a storekeeper has no work queue, and
             saying so is better than an empty list they will read as a bug. --}}
        <div class="p-3">
            <div class="alert alert-secondary mb-0">{{ __('work_order.not_a_technician') }}</div>
        </div>
    @elseif ($workOrders->isEmpty())
        <div class="p-3">
            <x-empty-state :title="__('work_order.no_my_work')"
                           :description="__('work_order.no_my_work_hint')" />
        </div>
    @else
        <div class="list-group list-group-flush">
            @foreach ($workOrders as $workOrder)
                {{-- The whole row is the target, not a small link inside it:
                     this is tapped in gloves (Frontend 6.2). --}}
                <a class="list-group-item list-group-item-action py-3"
                   href="{{ route('app.work-orders.show', $workOrder) }}">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="fw-semibold">{{ $workOrder->asset?->asset_code }}</span>
                        <span class="ms-auto">
                            @include('work_order::work-orders._priority', ['priority' => $workOrder->priority])
                        </span>
                    </div>

                    <div>{{ $workOrder->title }}</div>

                    <div class="d-flex align-items-center gap-2 mt-1">
                        @include('work_order::work-orders._status', ['status' => $workOrder->status])

                        {{-- The number is how a technician refers to the job on the
                             radio or to a storekeeper, so it stays on the row. --}}
                        <span class="small text-body-secondary">
                            {{ $workOrder->work_order_number }} · {{ $workOrder->maintenanceType?->name }}
                        </span>

                        @if ($workOrder->scheduled_start !== null)
                            <span class="ms-auto small {{ $workOrder->scheduled_start->isPast() ? 'text-danger' : 'text-body-secondary' }}">
                                {{ $workOrder->scheduled_start->toDateString() }}
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
