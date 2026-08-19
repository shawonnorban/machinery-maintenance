{{--
    Parts on a work order (SRS 19, ERD Section 13).

    Issued, fitted and returned are shown separately because they are separate
    facts. The "unaccounted" column is the one that matters: stock taken from
    the store that is neither in the machine nor back on the shelf. The job
    cannot close while it is above zero.
--}}
<div class="card mb-3">
    <div class="card-header">
        <i class="cil-storage" aria-hidden="true"></i>
        <span>{{ __('inventory.parts') }}</span>

        @if ($showCosts)
            <span class="ms-auto">
                {{ __('work_order.parts') }}:
                <strong>{{ $workOrder->actual_parts_cost }} {{ $workOrder->currency }}</strong>
            </span>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('inventory.spare_part') }}</th>
                    <th class="text-end">{{ __('inventory.quantity_issued') }}</th>
                    <th class="text-end">{{ __('inventory.quantity_consumed') }}</th>
                    <th class="text-end">{{ __('inventory.quantity_returned') }}</th>
                    <th class="text-end">{{ __('inventory.outstanding') }}</th>
                    @if ($showCosts)
                        <th class="text-end">{{ __('inventory.unit_cost') }}</th>
                    @endif
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($partLines as $line)
                    @php $outstanding = $line->outstandingQuantity(); @endphp

                    <tr class="{{ bccomp($outstanding, '0', 4) > 0 ? 'table-warning' : '' }}">
                        <td>
                            <a href="{{ route('app.inventory.parts.show', $line->spare_part_id) }}">
                                {{ $line->sparePart?->part_number }}
                            </a>
                            <div class="text-body-secondary">{{ $line->sparePart?->name }}</div>

                            @if ($line->substituteFor !== null)
                                {{-- What was fitted, and what it stood in for.
                                     Recording only the part used loses the reason
                                     a machine failed early (SRS 20). --}}
                                <div class="small text-body-secondary">
                                    {{ __('inventory.substitute_for') }}: {{ $line->substituteFor->part_number }}
                                </div>
                            @endif
                        </td>
                        <td class="text-end">{{ $line->quantity_issued }}</td>
                        <td class="text-end">{{ $line->quantity_consumed }}</td>
                        <td class="text-end">{{ $line->quantity_returned }}</td>
                        <td class="text-end {{ bccomp($outstanding, '0', 4) > 0 ? 'fw-semibold text-danger' : 'text-body-secondary' }}">
                            {{ $outstanding }}
                        </td>
                        @if ($showCosts)
                            <td class="text-end">{{ $line->unit_cost ?? '—' }}</td>
                        @endif
                        <td class="text-end">
                            @if (bccomp($outstanding, '0', 4) > 0 && ! $workOrder->isTerminal())
                                <div class="d-flex gap-1 justify-content-end">
                                    @can('inventory.stock.issue')
                                        <form method="POST"
                                              action="{{ route('app.work-orders.parts.consume', [$workOrder, $line->id]) }}"
                                              class="d-flex gap-1">
                                            @csrf
                                            <input name="quantity" type="number" step="0.0001" min="0.0001"
                                                   max="{{ $outstanding }}" value="{{ $outstanding }}"
                                                   class="form-control form-control-sm" style="width: 5.5rem"
                                                   aria-label="{{ __('inventory.consume') }}">
                                            <button class="btn btn-sm btn-outline-success">
                                                {{ __('inventory.consume') }}
                                            </button>
                                        </form>
                                    @endcan

                                    @can('inventory.stock.return')
                                        <form method="POST"
                                              action="{{ route('app.work-orders.parts.return', [$workOrder, $line->id]) }}"
                                              class="d-flex gap-1">
                                            @csrf
                                            <input name="quantity" type="number" step="0.0001" min="0.0001"
                                                   max="{{ $outstanding }}" value="{{ $outstanding }}"
                                                   class="form-control form-control-sm" style="width: 5.5rem"
                                                   aria-label="{{ __('inventory.return') }}">
                                            <button class="btn btn-sm btn-outline-secondary">
                                                {{ __('inventory.return') }}
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showCosts ? 7 : 6 }}" class="text-body-secondary">
                            {{ __('inventory.no_parts_on_work_order') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($partLines->contains(fn ($line) => bccomp($line->outstandingQuantity(), '0', 4) > 0))
        <div class="card-footer small text-body-secondary">{{ __('inventory.outstanding_hint') }}</div>
    @endif

    @canany(['inventory.stock.issue', 'inventory.reservation.manage'])
        @unless ($workOrder->isTerminal())
            <div class="card-body border-top">
                @can('inventory.stock.issue')
                    <form method="POST" action="{{ route('app.work-orders.parts.issue', $workOrder) }}"
                          class="row g-2 align-items-end">
                        @csrf

                        <div class="col-md-4">
                            <label for="issue_spare_part_id" class="form-label mb-1">
                                {{ __('inventory.spare_part') }}
                            </label>
                            <select id="issue_spare_part_id" name="spare_part_id" class="form-select form-select-sm"
                                    required data-tom-select>
                                <option value="">—</option>
                                @foreach ($spareParts as $sparePart)
                                    <option value="{{ $sparePart->id }}">
                                        {{ $sparePart->part_number }} — {{ $sparePart->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="issue_bin_id" class="form-label mb-1">{{ __('inventory.bin') }}</label>
                            <select id="issue_bin_id" name="bin_id" class="form-select form-select-sm" required>
                                @foreach ($bins as $bin)
                                    <option value="{{ $bin->id }}">{{ $bin->fullPath() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="issue_quantity" class="form-label mb-1">{{ __('inventory.quantity') }}</label>
                            <input id="issue_quantity" name="quantity" type="number" step="0.0001" min="0.0001"
                                   class="form-control form-control-sm" required>
                        </div>

                        <div class="col-md-2">
                            <button class="btn btn-sm btn-info text-white w-100">{{ __('inventory.issue') }}</button>
                        </div>
                    </form>
                @endcan

                @if ($reservations->isNotEmpty())
                    <div class="mt-3 border-top pt-3">
                        @foreach ($reservations as $reservation)
                            <div class="d-flex align-items-center gap-2 small">
                                <span>{{ $reservation->sparePart?->part_number }}</span>
                                <span class="text-body-secondary">
                                    {{ __('inventory.reserved') }} {{ $reservation->outstanding() }}
                                </span>

                                @can('inventory.reservation.manage')
                                    <form method="POST" class="ms-auto"
                                          action="{{ route('app.work-orders.reservations.release', [$workOrder, $reservation->id]) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            {{ __('inventory.release') }}
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endunless
    @endcanany
</div>
