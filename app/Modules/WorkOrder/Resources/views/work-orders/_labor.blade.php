{{--
    Time on the job (ADR-050).

    Time, and nothing but time. Technicians are salaried employees, so an hour
    of theirs carries no cost of its own and there is no money on this panel.
    What it answers is who did the work and how long it took, which is what
    workload and technician performance are built from (SRS 3.3).

    A contractor's charge is money that genuinely leaves the business and is
    recorded as a cost entry against the machine, where the invoice is.
--}}
<div class="card mb-3">
    <div class="card-header">
        <i class="cil-clock" aria-hidden="true"></i>
        <span>{{ __('work_order.labor') }}</span>

        <span class="ms-auto">
            {{ __('work_order.total_time') }}:
            <strong>{{ number_format($workOrder->laborEntries->sum('minutes')) }} {{ __('work_order.minutes') }}</strong>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('work_order.technician') }}</th>
                    <th>{{ __('work_order.started_at') }}</th>
                    <th>{{ __('work_order.ended_at') }}</th>
                    <th class="text-end">{{ __('work_order.minutes') }}</th>
                    <th>{{ __('work_order.notes') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workOrder->laborEntries as $entry)
                    <tr>
                        <td>{{ $entry->technician?->name }}</td>
                        <td>@dt($entry->started_at)</td>
                        <td>@dt($entry->ended_at)</td>
                        <td class="text-end">{{ number_format($entry->minutes) }}</td>
                        <td class="small text-body-secondary">{{ $entry->notes }}</td>
                        <td class="text-end">
                            @can('work_order.labor.manage')
                                @unless ($workOrder->isTerminal())
                                    <form method="POST"
                                          action="{{ route('app.work-orders.labor.destroy', [$workOrder, $entry->id]) }}"
                                          data-confirm="{{ __('common.confirm_delete') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}">
                                            <i class="cil-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-body-secondary">{{ __('work_order.no_labor') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @can('work_order.labor.manage')
        @unless ($workOrder->isTerminal())
            <div class="card-body border-top">
                <form method="POST" action="{{ route('app.work-orders.labor.store', $workOrder) }}"
                      class="row g-2 align-items-end">
                    @csrf

                    <div class="col-md-3">
                        <label for="labor_technician_id" class="form-label mb-1">{{ __('work_order.technician') }}</label>
                        <select id="labor_technician_id" name="technician_id" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="labor_started_at" class="form-label mb-1">{{ __('work_order.started_at') }}</label>
                        <input id="labor_started_at" name="started_at" type="datetime-local"
                               class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-3">
                        <label for="labor_ended_at" class="form-label mb-1">{{ __('work_order.ended_at') }}</label>
                        <input id="labor_ended_at" name="ended_at" type="datetime-local"
                               class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-2">
                        <label for="labor_notes" class="form-label mb-1">{{ __('work_order.notes') }}</label>
                        <input id="labor_notes" name="notes" type="text" class="form-control form-control-sm"
                               maxlength="500">
                    </div>

                    <div class="col-md-1">
                        <button class="btn btn-sm btn-info text-white w-100">{{ __('work_order.record') }}</button>
                    </div>
                </form>
            </div>
        @endunless
    @endcan
</div>
