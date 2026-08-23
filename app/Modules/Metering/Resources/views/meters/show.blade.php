@extends('layouts.app')
@section('title', $meter->type?->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.meters.index') }}">{{ __('metering.meters') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $meter->asset?->asset_code }}</li>
@endsection

@section('content')
    <x-page-header :title="$meter->type?->name"
                   :subtitle="$meter->asset?->asset_code.' — '.$meter->asset?->name">
        <x-slot:actions>
            <a href="{{ route('app.meters.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="cil-arrow-left" aria-hidden="true"></i> {{ __('common.back') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-body-secondary small">{{ __('metering.current_value') }}</div>
                    <div class="h3 mb-0">{{ $meter->current_value }} {{ $meter->type?->unit }}</div>
                    <div class="small text-body-secondary">
                        @if ($meter->last_reading_at)
                            {{ __('metering.last_read_at') }} @dt($meter->last_reading_at)
                        @else
                            {{ __('metering.never_read') }}
                        @endif
                    </div>
                </div>

                @can('meter.reading.create')
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('app.meters.readings', $meter) }}" class="row g-2">
                            @csrf

                            <div class="col-12 fw-semibold">{{ __('metering.record_reading') }}</div>

                            <div class="col-6">
                                <label for="value" class="form-label mb-1">{{ __('metering.value') }}</label>
                                <input id="value" name="value" type="number" step="0.0001" min="0"
                                       class="form-control" value="{{ old('value') }}" required autofocus>
                                @error('value')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-6">
                                <label for="reading_at" class="form-label mb-1">{{ __('metering.read_at') }}</label>
                                {{-- Typed on the factory's clock. Storing what the
                                     person meant, not what the server's clock says. --}}
                                <input id="reading_at" name="reading_at" type="datetime-local"
                                       class="form-control" value="{{ old('reading_at', $now) }}">
                                @error('reading_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label mb-1">{{ __('metering.notes') }}</label>
                                <input id="notes" name="notes" type="text" class="form-control" maxlength="500">
                            </div>

                            <div class="col-12">
                                <button class="btn btn-info text-white">{{ __('metering.save_reading') }}</button>
                            </div>
                        </form>
                    </div>
                @endcan

                @can('meter.meter.reset')
                    <div class="card-body border-top">
                        {{-- The one legitimate way a cumulative reading goes down.
                             Recorded as its own event so the drop has an
                             explanation and consumption reporting can bridge it. --}}
                        <details>
                            <summary class="small text-body-secondary">{{ __('metering.replace_meter') }}</summary>

                            <form method="POST" action="{{ route('app.meters.reset', $meter) }}" class="row g-2 mt-2">
                                @csrf

                                <div class="col-12">
                                    <label for="new_value" class="form-label mb-1">{{ __('metering.new_value') }}</label>
                                    <input id="new_value" name="new_value" type="number" step="0.0001" min="0"
                                           class="form-control form-control-sm" value="0" required>
                                </div>

                                <div class="col-12">
                                    <label for="reason" class="form-label mb-1">{{ __('metering.reason') }}</label>
                                    <input id="reason" name="reason" type="text" class="form-control form-control-sm"
                                           required maxlength="500">
                                    @error('reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-12">
                                    <button class="btn btn-sm btn-outline-danger">{{ __('metering.record_replacement') }}</button>
                                </div>
                            </form>
                        </details>
                    </div>
                @endcan
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">{{ __('metering.reading_history') }}</div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('metering.read_at') }}</th>
                                <th class="text-end">{{ __('metering.value') }}</th>
                                <th class="text-end">{{ __('metering.consumed') }}</th>
                                <th>{{ __('metering.source') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($readings as $reading)
                                <tr @class(['table-warning' => $reading->is_reset_baseline])>
                                    <td>@dt($reading->reading_at)</td>
                                    <td class="text-end">{{ $reading->value }}</td>
                                    <td class="text-end">
                                        @if ($reading->is_reset_baseline)
                                            {{-- A replacement, not consumption: the
                                                 drop is explained rather than counted. --}}
                                            <span class="badge bg-warning text-dark">
                                                {{ __('metering.replacement') }}
                                            </span>
                                        @else
                                            {{ $reading->delta ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="small text-body-secondary">
                                        {{ $reading->source }}
                                        @if ($reading->notes)
                                            <div>{{ $reading->notes }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-body-secondary">{{ __('metering.no_readings') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
