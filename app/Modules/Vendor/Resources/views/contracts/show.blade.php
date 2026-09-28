@extends('layouts.app')
@section('title', $contract->contract_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.service-contracts.index') }}">{{ __('vendor.contracts') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $contract->contract_number }}</li>
@endsection

@section('content')
    @php $days = $contract->daysRemaining(); @endphp

    <x-page-header :title="$contract->contract_number"
                   :subtitle="__('vendor.contract_type_'.strtolower($contract->contract_type))" />

    @if ($contract->status === 'ACTIVE' && $days >= 0 && $days <= 60)
        <div class="alert alert-warning">
            {{ __('vendor.days_remaining') }}: <strong>{{ $days }}</strong> —
            {{ $contract->end_date->format('Y-m-d') }}
        </div>
    @endif

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('vendor.vendor') }}</dt>
                        <dd class="col-7">
                            <a href="{{ route('app.vendors.show', $contract->vendor) }}">{{ $contract->vendor?->name }}</a>
                        </dd>

                        <dt class="col-5">{{ __('vendor.status') }}</dt>
                        <dd class="col-7">
                            <x-status-pill :status="$contract->status" :tone="match ($contract->status) {
                                'ACTIVE' => 'success',
                                'CANCELLED' => 'danger',
                                'RENEWED' => 'info',
                                default => 'secondary',
                            }">
                                {{ __('vendor.contract_status_'.strtolower($contract->status)) }}
                            </x-status-pill>
                        </dd>

                        <dt class="col-5">{{ __('vendor.scope') }}</dt>
                        <dd class="col-7">
                            @if ($contract->asset_id)
                                {{ $contract->asset?->asset_code }} — {{ $contract->asset?->name }}
                            @elseif ($contract->factory_id)
                                {{ $contract->factory?->name }}
                            @else
                                {{ __('vendor.covers_machines') }}: {{ $contract->assets->count() }}
                            @endif
                        </dd>

                        <dt class="col-5">{{ __('vendor.start_date') }}</dt>
                        <dd class="col-7">{{ $contract->start_date->format('Y-m-d') }}</dd>

                        <dt class="col-5">{{ __('vendor.end_date') }}</dt>
                        <dd class="col-7">{{ $contract->end_date->format('Y-m-d') }}</dd>

                        <dt class="col-5">{{ __('vendor.renewal_date') }}</dt>
                        <dd class="col-7">{{ $contract->renewal_date?->format('Y-m-d') ?? '—' }}</dd>

                        <dt class="col-5">{{ __('vendor.value') }}</dt>
                        <dd class="col-7">
                            {{ $contract->value === null ? '—' : number_format((float) $contract->value, 2).' '.$contract->currency }}
                        </dd>

                        <dt class="col-5">{{ __('vendor.visits_per_year') }}</dt>
                        <dd class="col-7">{{ $contract->visits_per_year ?? '—' }}</dd>

                        <dt class="col-5">{{ __('vendor.response_time_hours') }}</dt>
                        <dd class="col-7">{{ $contract->response_time_hours ?? '—' }}</dd>

                        <dt class="col-5">{{ __('vendor.coverage') }}</dt>
                        <dd class="col-7">{{ $contract->coverage ?: '—' }}</dd>

                        @if ($contract->renewedFrom)
                            <dt class="col-5">{{ __('vendor.renewed_from') }}</dt>
                            <dd class="col-7">
                                <a href="{{ route('app.service-contracts.show', $contract->renewedFrom) }}">
                                    {{ $contract->renewedFrom->contract_number }}
                                </a>
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            @can('update', $contract)
                @if (! in_array($contract->status, ['CANCELLED', 'RENEWED'], true))
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="cil-loop-circular" aria-hidden="true"></i>
                            <span>{{ __('vendor.renew') }}</span>
                        </div>

                        <div class="card-body">
                            {{-- A renewal is a new contract. Editing these dates
                                 in place would erase what was signed last year,
                                 which is the only thing an AMC history is for. --}}
                            <p class="small text-body-secondary">{{ __('vendor.renew_hint') }}</p>

                            <form method="POST" action="{{ route('app.service-contracts.renew', $contract) }}"
                                  class="row g-2">
                                @csrf

                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">{{ __('vendor.start_date') }}</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date"
                                           value="{{ $contract->end_date->addDay()->format('Y-m-d') }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">{{ __('vendor.end_date') }}</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date"
                                           value="{{ $contract->end_date->addYear()->format('Y-m-d') }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label for="value" class="form-label">{{ __('vendor.value') }}</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="value"
                                           name="value" value="{{ $contract->value }}">
                                </div>

                                <div class="col-12">
                                    <button class="btn btn-sm btn-info text-white">{{ __('vendor.renew') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="POST" action="{{ route('app.service-contracts.cancel', $contract) }}"
                                  class="row g-2">
                                @csrf

                                <div class="col-md-8">
                                    <label for="reason" class="form-label">{{ __('vendor.cancel_reason') }}</label>
                                    <input type="text" class="form-control @error('reason') is-invalid @enderror"
                                           id="reason" name="reason" required>
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-4 d-flex align-items-end">
                                    <button class="btn btn-sm btn-outline-danger">{{ __('vendor.cancel_contract') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan

            @if ($contract->assets->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="cil-settings" aria-hidden="true"></i>
                        <span>{{ __('vendor.covers_machines') }}</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <tbody>
                                @foreach ($contract->assets as $asset)
                                    <tr>
                                        <td>{{ $asset->asset_code }}</td>
                                        <td>{{ $asset->name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
