<h2 class="h6 text-body-secondary text-uppercase mb-2">{{ __('dashboard.store') }}</h2>

<div class="row">
    <div class="col-sm-6 col-xl-3">
        <x-kpi-tile :label="__('dashboard.stock_value')"
                    :value="number_format((float) $data['stock_value'], 0)" tone="primary" />
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-kpi-tile :label="__('dashboard.low_stock')"
                    :value="number_format($data['low_stock'])"
                    :caption="__('dashboard.low_stock_hint')"
                    :tone="$data['low_stock'] > 0 ? 'warning' : 'success'" />
    </div>

    <div class="col-sm-6 col-xl-3">
        {{-- Separate from low stock: a critical spare running out stops a
             critical machine, which is a different problem from a box of
             washers running low. --}}
        <x-kpi-tile :label="__('dashboard.critical_low')"
                    :value="number_format($data['critical_low'])"
                    :caption="__('dashboard.critical_low_hint')"
                    :tone="$data['critical_low'] > 0 ? 'danger' : 'success'" />
    </div>

    <div class="col-sm-6 col-xl-3">
        <x-kpi-tile :label="__('dashboard.out_of_stock')"
                    :value="number_format($data['out_of_stock'])"
                    :tone="$data['out_of_stock'] > 0 ? 'danger' : 'success'" />
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <i class="cil-storage" aria-hidden="true"></i>
        <span>{{ __('dashboard.store') }}</span>

        <a href="{{ route('app.inventory.low-stock') }}" class="btn btn-sm btn-outline-warning ms-auto">
            {{ __('dashboard.low_stock') }}
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <tbody>
                <tr>
                    <td>{{ __('dashboard.reserved') }}</td>
                    <td class="text-end">{{ rtrim(rtrim($data['reserved_quantity'], '0'), '.') ?: '0' }}</td>
                </tr>
                <tr>
                    <td>{{ __('dashboard.active_reservations') }}</td>
                    <td class="text-end">{{ number_format($data['active_reservations']) }}</td>
                </tr>
                <tr>
                    <td>{{ __('dashboard.parts_issued') }}</td>
                    <td class="text-end">{{ number_format((float) $data['issued_value'], 0) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
