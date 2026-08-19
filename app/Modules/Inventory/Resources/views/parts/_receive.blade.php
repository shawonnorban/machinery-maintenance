@canany(['inventory.stock.receive', 'inventory.adjustment.create'])
    <div class="card mb-3">
        <div class="card-header">
            <i class="cil-arrow-circle-bottom" aria-hidden="true"></i>
            <span>{{ __('inventory.receive') }} / {{ __('inventory.adjust') }}</span>
        </div>

        <div class="card-body">
            @can('inventory.stock.receive')
                <form method="POST" action="{{ route('app.inventory.stock.receive') }}" class="row g-2 align-items-end mb-3">
                    @csrf
                    <input type="hidden" name="spare_part_id" value="{{ $part->id }}">

                    <div class="col-md-4">
                        <label for="receive_bin_id" class="form-label mb-1">{{ __('inventory.bin') }}</label>
                        <select id="receive_bin_id" name="bin_id" class="form-select form-select-sm" required>
                            @foreach ($bins as $bin)
                                <option value="{{ $bin->id }}">{{ $bin->fullPath() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="receive_quantity" class="form-label mb-1">{{ __('inventory.quantity') }}</label>
                        <input id="receive_quantity" name="quantity" type="number" step="0.0001" min="0.0001"
                               class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-2">
                        <label for="receive_unit_cost" class="form-label mb-1">{{ __('inventory.unit_cost') }}</label>
                        {{-- Required rather than defaulted. Receiving at zero
                             would drag the average down and make every later
                             issue look free. --}}
                        <input id="receive_unit_cost" name="unit_cost" type="number" step="0.0001" min="0"
                               class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-2">
                        <label for="receive_type" class="form-label mb-1">{{ __('inventory.transaction_type') }}</label>
                        <select id="receive_type" name="transaction_type" class="form-select form-select-sm">
                            @foreach (['RECEIPT', 'OPENING_BALANCE', 'ADJUSTMENT_IN'] as $type)
                                <option value="{{ $type }}">{{ __('inventory.type_'.strtolower($type)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-sm btn-info text-white w-100">{{ __('inventory.receive') }}</button>
                    </div>

                    <div class="col-12">
                        <input name="notes" type="text" class="form-control form-control-sm"
                               maxlength="2000" placeholder="{{ __('inventory.notes') }}">
                    </div>
                </form>
            @endcan

            @can('inventory.adjustment.create')
                <form method="POST" action="{{ route('app.inventory.stock.adjust') }}" class="row g-2 align-items-end border-top pt-3">
                    @csrf
                    <input type="hidden" name="spare_part_id" value="{{ $part->id }}">

                    <div class="col-md-4">
                        <label for="adjust_bin_id" class="form-label mb-1">{{ __('inventory.bin') }}</label>
                        <select id="adjust_bin_id" name="bin_id" class="form-select form-select-sm" required>
                            @foreach ($bins as $bin)
                                <option value="{{ $bin->id }}">{{ $bin->fullPath() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="adjust_quantity" class="form-label mb-1">{{ __('inventory.quantity') }}</label>
                        <input id="adjust_quantity" name="quantity" type="number" step="0.0001" min="0.0001"
                               class="form-control form-control-sm" required>
                    </div>

                    <div class="col-md-2">
                        <label for="adjust_type" class="form-label mb-1">{{ __('inventory.transaction_type') }}</label>
                        <select id="adjust_type" name="transaction_type" class="form-select form-select-sm">
                            @foreach (['ADJUSTMENT_OUT', 'SCRAP'] as $type)
                                <option value="{{ $type }}">{{ __('inventory.type_'.strtolower($type)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="adjust_notes" class="form-label mb-1">{{ __('inventory.reason') }}</label>
                        <input id="adjust_notes" name="notes" type="text" class="form-control form-control-sm"
                               required maxlength="2000">
                    </div>

                    <div class="col-12 d-flex align-items-center gap-3">
                        {{-- Stock that moves without an explanation is
                             indistinguishable from loss. --}}
                        <div class="form-text mb-0">{{ __('inventory.adjustment_needs_reason') }}</div>
                        <button class="btn btn-sm btn-outline-danger ms-auto">{{ __('inventory.adjust') }}</button>
                    </div>
                </form>
            @endcan
        </div>
    </div>
@endcanany
