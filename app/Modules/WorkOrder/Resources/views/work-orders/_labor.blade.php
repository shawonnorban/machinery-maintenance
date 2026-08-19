{{--
    Labour entries (ADR-050, ADR-065).

    There is no rate field for internal work. The rate comes from the
    technician's grade, effective on the day the work happened, resolved
    server-side. Offering the field would let anyone set what the work cost, and
    it would turn a maintenance screen into a place where colleagues' pay is
    visible (SRS 3.3).
--}}
<div class="card mb-3">
    <div class="card-header">
        <i class="cil-clock" aria-hidden="true"></i>
        <span>{{ __('work_order.labor') }}</span>

        @if ($showCosts)
            <span class="ms-auto">
                {{ __('work_order.total_labor') }}:
                <strong>{{ $workOrder->actual_labor_cost }} {{ $workOrder->currency }}</strong>
            </span>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('work_order.technician') }}</th>
                    <th>{{ __('work_order.labor_category') }}</th>
                    <th>{{ __('work_order.started_at') }}</th>
                    <th>{{ __('work_order.ended_at') }}</th>
                    <th class="text-end">{{ __('work_order.minutes') }}</th>
                    @if ($showCosts)
                        <th class="text-end">{{ __('work_order.amount') }}</th>
                    @endif
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($workOrder->laborEntries as $entry)
                    <tr>
                        <td>{{ $entry->technician?->name ?? __('work_order.labor_category_external') }}</td>
                        <td>{{ __('work_order.labor_category_'.strtolower($entry->labor_category)) }}</td>
                        <td>@dt($entry->started_at)</td>
                        <td>@dt($entry->ended_at)</td>
                        <td class="text-end">{{ number_format($entry->minutes) }}</td>
                        @if ($showCosts)
                            <td class="text-end">{{ $entry->amount }}</td>
                        @endif
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
                        <td colspan="{{ $showCosts ? 7 : 6 }}" class="text-body-secondary">
                            {{ __('work_order.no_labor') }}
                        </td>
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
                        <select id="labor_technician_id" name="technician_id" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="labor_category" class="form-label mb-1">{{ __('work_order.labor_category') }}</label>
                        <select id="labor_category" name="labor_category" class="form-select form-select-sm" required>
                            @foreach (['REGULAR', 'OVERTIME', 'EXTERNAL'] as $option)
                                <option value="{{ $option }}">
                                    {{ __('work_order.labor_category_'.strtolower($option)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="labor_started_at" class="form-label mb-1">{{ __('work_order.started_at') }}</label>
                        <input id="labor_started_at" name="started_at" type="datetime-local"
                               class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-2">
                        <label for="labor_ended_at" class="form-label mb-1">{{ __('work_order.ended_at') }}</label>
                        <input id="labor_ended_at" name="ended_at" type="datetime-local"
                               class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-2">
                        <label for="labor_hourly_rate" class="form-label mb-1">{{ __('work_order.hourly_rate') }}</label>
                        {{-- Read only for EXTERNAL: a contractor's charge is an
                             invoiced amount, not employee compensation. --}}
                        <input id="labor_hourly_rate" name="hourly_rate" type="number" step="0.0001" min="0"
                               class="form-control form-control-sm"
                               placeholder="{{ __('work_order.labor_category_external') }}">
                    </div>

                    <div class="col-md-1">
                        <button class="btn btn-sm btn-info text-white w-100">{{ __('work_order.record') }}</button>
                    </div>

                    <div class="col-12">
                        <div class="form-text">{{ __('work_order.labor_grade_note') }}</div>
                    </div>
                </form>
            </div>
        @endunless
    @endcan
</div>
