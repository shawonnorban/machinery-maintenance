@extends('layouts.app')
@section('title', $asset->asset_code)

@section('content')
    <x-page-header :title="$asset->asset_code" :subtitle="$asset->name">
        <x-slot:actions>
            @can('update', $asset)
                <a href="{{ route('app.assets.edit', $asset) }}" class="btn btn-outline-secondary">
                    {{ __('asset.edit_asset') }}
                </a>
            @endcan
            @can('transfer', $asset)
                <a href="{{ route('app.assets.transfer.create', $asset) }}" class="btn btn-outline-primary">
                    {{ __('asset.transfer') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($coverage['covered'])
        {{-- Before anything else, because it changes what happens next: this
             repair may already be paid for. A machine repaired at the factory's
             own cost while under warranty is money thrown away (SRS 26). --}}
        <div class="alert alert-success d-flex flex-wrap gap-3 align-items-center">
            <span>
                @if ($coverage['warranty'])
                    {{ __('vendor.covered_by_warranty', [
                        'vendor' => $coverage['warranty']->vendor?->name ?? __('vendor.unnamed_vendor'),
                        'until' => $coverage['warranty']->end_date->format('Y-m-d'),
                    ]) }}
                @else
                    @php $contract = $coverage['contracts']->first(); @endphp
                    {{ __('vendor.covered_by_contract', [
                        'vendor' => $contract->vendor?->name ?? __('vendor.unnamed_vendor'),
                        'number' => $contract->contract_number,
                        'until' => $contract->end_date->format('Y-m-d'),
                    ]) }}
                @endif
            </span>

            @if ($coverage['warranty'])
                <a href="{{ route('app.warranties.show', $coverage['warranty']) }}"
                   class="btn btn-sm btn-outline-success ms-auto">
                    {{ __('vendor.warranty') }}
                </a>
            @endif
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            {{-- Above the fold: status, location, next PM, open breakdown and
                 lifetime cost. Those answer most reasons anyone opens an asset
                 (Frontend 5.3). --}}
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center gap-2">
                    {{ __('asset.overview') }}
                    <span class="ms-auto d-flex gap-2">
                        @include('asset::assets._criticality', ['criticality' => $asset->criticality])
                        @include('asset::assets._status', ['status' => $asset->status])
                    </span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('asset.type') }}</dt>
                        <dd class="col-sm-8">{{ $asset->type?->name }} &middot; {{ $asset->category?->name }}</dd>

                        <dt class="col-sm-4">{{ __('asset.manufacturer') }}</dt>
                        <dd class="col-sm-8">{{ $asset->manufacturer?->name ?? '—' }} {{ $asset->model?->model }}</dd>

                        <dt class="col-sm-4">{{ __('asset.serial_number') }}</dt>
                        <dd class="col-sm-8"><code>{{ $asset->serial_number ?? '—' }}</code></dd>

                        <dt class="col-sm-4">{{ __('asset.factory') }}</dt>
                        <dd class="col-sm-8">{{ $asset->factory?->name }}</dd>

                        <dt class="col-sm-4">{{ __('asset.location') }}</dt>
                        <dd class="col-sm-8">{{ $asset->location?->full_path ?: $asset->location?->name }}</dd>

                        <dt class="col-sm-4">{{ __('asset.qr_code') }}</dt>
                        <dd class="col-sm-8"><code>{{ $asset->qr_code }}</code></dd>

                        @if ($asset->parent)
                            <dt class="col-sm-4">{{ __('asset.parent_asset') }}</dt>
                            <dd class="col-sm-8">
                                <a href="{{ route('app.assets.show', $asset->parent) }}">
                                    {{ $asset->parent->asset_code }}
                                </a>
                            </dd>
                        @endif

                        <dt class="col-sm-4">{{ __('asset.warranty') }}</dt>
                        <dd class="col-sm-8">
                            @if ($asset->warranty_end)
                                {{ $asset->warranty_end->toDateString() }}
                                <x-status-pill
                                    :status="$asset->warrantyIsActive() ? 'ACTIVE' : 'EXPIRED'"
                                    :tone="$asset->warrantyIsActive() ? 'success' : 'secondary'">
                                    {{ $asset->warrantyIsActive() ? __('asset.warranty_active') : __('asset.warranty_expired') }}
                                </x-status-pill>
                            @else
                                &mdash;
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            @if ($asset->children->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header">{{ __('asset.child_assets') }}</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 align-middle">
                            <tbody>
                                @foreach ($asset->children as $child)
                                    <tr>
                                        <td><a href="{{ route('app.assets.show', $child) }}">{{ $child->asset_code }}</a></td>
                                        <td>{{ $child->name }}</td>
                                        <td>@include('asset::assets._status', ['status' => $child->status])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="card mb-4">
                <div class="card-header">{{ __('asset.history') }}</div>
                <div class="card-body p-0">
                    @if ($statusHistory->isEmpty())
                        <x-empty-state :title="__('asset.no_history')" />
                    @else
                        <table class="table table-sm mb-0 align-middle">
                            <tbody>
                                @foreach ($statusHistory as $entry)
                                    <tr>
                                        <td class="small text-body-secondary" style="width: 11rem">
                                            {{ $entry->changed_at?->toDayDateTimeString() }}
                                        </td>
                                        <td>
                                            @if ($entry->from_status)
                                                @include('asset::assets._status', ['status' => $entry->from_status])
                                                <span aria-hidden="true">→</span>
                                            @endif
                                            @include('asset::assets._status', ['status' => $entry->to_status])
                                        </td>
                                        <td class="small">{{ $entry->reason }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">{{ __('asset.transfers') }}</div>
                <div class="card-body p-0">
                    @if ($transfers->isEmpty())
                        <x-empty-state :title="__('asset.no_transfers')" />
                    @else
                        <table class="table table-sm mb-0 align-middle">
                            <tbody>
                                @foreach ($transfers as $transfer)
                                    <tr>
                                        <td class="small"><code>{{ $transfer->transfer_number }}</code></td>
                                        <td class="small">
                                            {{ $transfer->fromFactory?->name }}
                                            <span aria-hidden="true">→</span>
                                            {{ $transfer->toFactory?->name }} / {{ $transfer->toLocation?->name }}
                                        </td>
                                        <td>
                                            <x-status-pill :status="$transfer->status"
                                                :tone="$transfer->status === 'RECEIVED' ? 'success' : ($transfer->status === 'REJECTED' ? 'danger' : 'info')">
                                                {{ __('asset.transfer_status_'.strtolower($transfer->status)) }}
                                            </x-status-pill>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @can('changeStatus', $asset)
                <div class="card mb-4">
                    <div class="card-header">{{ __('asset.change_status') }}</div>
                    <div class="card-body">
                        @if (empty($allowedTransitions))
                            {{-- Terminal. Showing a disabled control with the
                                 reason beats an absent one (Frontend 9.1). --}}
                            <p class="text-body-secondary mb-0 small">
                                {{ __('asset.status_'.strtolower($asset->status)) }} —
                                {{ __('common.not_available') }}
                            </p>
                        @else
                            <form method="POST" action="{{ route('app.assets.status', $asset) }}">
                                @csrf
                                <input type="hidden" name="version" value="{{ $asset->version }}">

                                <div class="mb-3">
                                    <label for="status" class="form-label">{{ __('asset.move_to') }}</label>
                                    <select id="status" name="status" class="form-select" required>
                                        @foreach ($allowedTransitions as $next)
                                            <option value="{{ $next }}">{{ __('asset.status_'.strtolower($next)) }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="reason" class="form-label">{{ __('asset.reason') }}</label>
                                    <input id="reason" name="reason" type="text" class="form-control"
                                           maxlength="255">
                                    <div class="form-text">{{ __('asset.reason_required_terminal') }}</div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">
                                    {{ __('asset.change_status') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan


            <div class="card mb-4">
                <div class="card-header">{{ __('scan.qr_token') }}</div>
                <div class="card-body text-center">
                    {!! $qrSvg !!}
                    <div class="mt-2"><code>{{ $asset->qr_code }}</code></div>

                    <div class="d-grid gap-2 mt-3">
                        <a href="{{ route('app.assets.labels', ['ids' => [$asset->id]]) }}"
                           class="btn btn-sm btn-outline-secondary">{{ __('scan.labels') }}</a>

                        @can('regenerateQr', $asset)
                            <form method="POST" action="{{ route('app.assets.qr.regenerate', $asset) }}"
                                  data-confirm="{{ __('scan.regenerate_qr_confirm') }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                    {{ __('scan.regenerate_qr') }}
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
            @can('viewFinancial', $asset)
                <div class="card mb-4">
                    <div class="card-header">{{ __('asset.financial') }}</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-7">{{ __('asset.acquisition_cost') }}</dt>
                            <dd class="col-5 text-end">{{ $asset->acquisition_cost ?? '—' }}</dd>

                            <dt class="col-7">{{ __('asset.installation_cost') }}</dt>
                            <dd class="col-5 text-end">{{ $asset->installation_cost ?? '—' }}</dd>

                            <dt class="col-7">{{ __('asset.currency') }}</dt>
                            <dd class="col-5 text-end">{{ $asset->currency ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            @endcan

            <div class="card mb-4">
                <div class="card-body small text-body-secondary">
                    <div>{{ __('asset.purchase_date') }}: {{ $asset->purchase_date?->toDateString() ?? '—' }}</div>
                    <div>{{ __('asset.installation_date') }}: {{ $asset->installation_date?->toDateString() ?? '—' }}</div>
                    <div>{{ __('asset.commissioning_date') }}: {{ $asset->commissioning_date?->toDateString() ?? '—' }}</div>
                    <div>{{ __('asset.created_at') }}: {{ $asset->created_at?->toDateString() }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
