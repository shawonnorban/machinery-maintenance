@extends('layouts.app')
@section('title', __('vendor.warranty'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.warranties.index') }}">{{ __('vendor.warranties') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $warranty->asset?->asset_code }}</li>
@endsection

@section('content')
    @php $days = $warranty->daysRemaining(); @endphp

    <x-page-header :title="$warranty->asset?->asset_code ?? __('vendor.warranty')"
                   :subtitle="$warranty->asset?->name" />

    @if ($warranty->isActiveOn())
        {{-- The one sentence a technician needs: this repair is already paid
             for, by whom, until when. --}}
        <div class="alert alert-success">
            {{ __('vendor.covered_by_warranty', [
                'vendor' => $warranty->vendor?->name ?? __('vendor.unnamed_vendor'),
                'until' => $warranty->end_date->format('Y-m-d'),
            ]) }}
            @if ($days <= 30)
                — <strong>{{ __('vendor.days_remaining') }}: {{ $days }}</strong>
            @endif
        </div>
    @else
        <div class="alert alert-secondary">{{ __('vendor.not_covered') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('vendor.vendor') }}</dt>
                        <dd class="col-7">
                            @if ($warranty->vendor)
                                <a href="{{ route('app.vendors.show', $warranty->vendor) }}">{{ $warranty->vendor->name }}</a>
                            @else
                                {{ __('vendor.unnamed_vendor') }}
                            @endif
                        </dd>

                        <dt class="col-5">{{ __('vendor.warranty_type') }}</dt>
                        <dd class="col-7">{{ __('vendor.type_'.strtolower($warranty->warranty_type === 'SERVICE' ? 'service_warranty' : $warranty->warranty_type)) }}</dd>

                        <dt class="col-5">{{ __('vendor.reference') }}</dt>
                        <dd class="col-7">{{ $warranty->reference ?: '—' }}</dd>

                        <dt class="col-5">{{ __('vendor.start_date') }}</dt>
                        <dd class="col-7">{{ $warranty->start_date->format('Y-m-d') }}</dd>

                        <dt class="col-5">{{ __('vendor.end_date') }}</dt>
                        <dd class="col-7">{{ $warranty->end_date->format('Y-m-d') }}</dd>

                        <dt class="col-5">{{ __('vendor.coverage') }}</dt>
                        <dd class="col-7">{{ $warranty->coverage ?: '—' }}</dd>

                        <dt class="col-5">{{ __('vendor.exclusions') }}</dt>
                        <dd class="col-7">{{ $warranty->exclusions ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            @can('update', $warranty)
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="cil-file" aria-hidden="true"></i>
                        <span>{{ __('vendor.file_claim') }}</span>
                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('app.warranties.claims.store', $warranty) }}" class="row g-3">
                            @csrf

                            <div class="col-md-6">
                                <label for="claim_date" class="form-label">{{ __('vendor.claim_date') }}</label>
                                <input type="date" class="form-control" id="claim_date" name="claim_date"
                                       value="{{ old('claim_date', now()->format('Y-m-d')) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label for="incident_date" class="form-label">{{ __('vendor.incident_date') }}</label>
                                <input type="date" class="form-control" id="incident_date" name="incident_date"
                                       value="{{ old('incident_date') }}">
                                {{-- Cover is judged on the day the machine
                                     failed, not the day somebody got round to
                                     claiming. --}}
                                <div class="form-text">{{ __('vendor.incident_date_hint') }}</div>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">{{ __('vendor.description') }}</label>
                                <textarea class="form-control @error('description') is-invalid @enderror"
                                          id="description" name="description" rows="3" required>{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="claimed_amount" class="form-label">{{ __('vendor.claimed_amount') }}</label>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       id="claimed_amount" name="claimed_amount" value="{{ old('claimed_amount') }}">
                            </div>

                            <div class="col-12">
                                <button class="btn btn-info text-white">{{ __('vendor.file_claim') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="cil-list" aria-hidden="true"></i>
                    <span>{{ __('vendor.claims') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('vendor.claim_number') }}</th>
                                <th>{{ __('vendor.claim_date') }}</th>
                                <th class="text-end">{{ __('vendor.claimed_amount') }}</th>
                                <th class="text-end">{{ __('vendor.settled_amount') }}</th>
                                <th>{{ __('vendor.status') }}</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($claims as $claim)
                                <tr>
                                    <td>
                                        {{ $claim->claim_number }}
                                        <div class="small text-body-secondary">{{ $claim->description }}</div>
                                        @if ($claim->resolution)
                                            <div class="small">{{ __('vendor.resolution') }}: {{ $claim->resolution }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $claim->claim_date->format('Y-m-d') }}</td>
                                    <td class="text-end">{{ $claim->claimed_amount === null ? '—' : number_format((float) $claim->claimed_amount, 0) }}</td>
                                    <td class="text-end">{{ $claim->settled_amount === null ? '—' : number_format((float) $claim->settled_amount, 0) }}</td>
                                    <td>
                                        <x-status-pill :status="$claim->status" :tone="match ($claim->status) {
                                            'SETTLED' => 'success',
                                            'REJECTED' => 'danger',
                                            'APPROVED' => 'info',
                                            default => 'warning',
                                        }">
                                            {{ __('vendor.claim_status_'.strtolower($claim->status)) }}
                                        </x-status-pill>
                                    </td>
                                    <td class="text-end">
                                        @can('update', $claim)
                                            @if ($claim->isOpen())
                                                <form method="POST" action="{{ route('app.warranty-claims.decide', $claim) }}"
                                                      class="d-flex gap-1 justify-content-end">
                                                    @csrf

                                                    <select class="form-select form-select-sm" name="status" style="width:auto">
                                                        @foreach (App\Modules\Vendor\Models\WarrantyClaim::STATUSES as $status)
                                                            <option value="{{ $status }}">
                                                                {{ __('vendor.claim_status_'.strtolower($status)) }}
                                                            </option>
                                                        @endforeach
                                                    </select>

                                                    <input type="text" class="form-control form-control-sm"
                                                           name="resolution" placeholder="{{ __('vendor.resolution') }}"
                                                           style="width:12rem">

                                                    <input type="number" step="0.01" min="0"
                                                           class="form-control form-control-sm" name="settled_amount"
                                                           placeholder="{{ __('vendor.settled_amount') }}" style="width:8rem">

                                                    <button class="btn btn-sm btn-outline-primary">{{ __('common.save') }}</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-body-secondary">{{ __('vendor.no_claims') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
