{{-- What is counted on this machine (SRS 11).

     A usage-based plan hangs off one of these. Without a meter fitted, a plan
     that says "service every 500 running hours" has nothing to measure and
     never comes due — which is why this panel is on the machine's own screen
     rather than buried in configuration. --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ __('metering.meters') }}</span>

        @if ($meters->isNotEmpty())
            <a href="{{ route('app.meters.index', ['asset_id' => $asset->id]) }}" class="small">
                {{ __('metering.reading_history') }}
            </a>
        @endif
    </div>

    <div class="card-body p-0">
        @if ($meters->isEmpty())
            <div class="p-3 text-body-secondary small">{{ __('metering.none_on_this_machine') }}</div>
        @else
            <table class="table table-hover align-middle mb-0">
                <tbody>
                    @foreach ($meters as $meter)
                        <tr @class(['opacity-50' => $meter->status !== 'ACTIVE'])>
                            <td>
                                {{ $meter->type?->name }}
                                @unless ($meter->status === 'ACTIVE')
                                    <span class="badge bg-secondary">{{ __('metering.out_of_use') }}</span>
                                @endunless
                            </td>
                            <td class="text-end">
                                {{ $meter->current_value }} {{ $meter->type?->unit }}
                                <div class="small text-body-secondary">
                                    @if ($meter->last_reading_at)
                                        @dt($meter->last_reading_at)
                                    @else
                                        {{ __('metering.never_read') }}
                                    @endif
                                </div>
                            </td>
                            <td class="text-end">
                                @can('meter.reading.create')
                                    <a href="{{ route('app.meters.show', $meter) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        {{ __('metering.record_reading') }}
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @can('meter.meter.manage')
        @if ($meterTypes !== [])
            <div class="card-body border-top">
                <form method="POST" action="{{ route('app.assets.meters.attach', $asset) }}"
                      class="row g-2 align-items-end">
                    @csrf

                    <div class="col-md-6">
                        <label for="meter_type_id" class="form-label mb-1">{{ __('metering.fit_a_meter') }}</label>
                        <select id="meter_type_id" name="meter_type_id" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach ($meterTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->unit }})</option>
                            @endforeach
                        </select>
                        @error('meter_type_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="initial_value" class="form-label mb-1">{{ __('metering.reading_today') }}</label>
                        <input id="initial_value" name="initial_value" type="number" step="0.0001" min="0"
                               class="form-control form-control-sm" value="0">
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-sm btn-outline-info w-100">{{ __('metering.fit') }}</button>
                    </div>
                </form>
            </div>
        @endif
    @endcan
</div>
