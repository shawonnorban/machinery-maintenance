@extends('layouts.app')
@section('title', __('asset.pending_transfers'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.assets.index') }}">{{ __('asset.assets') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('asset.pending_transfers') }}</li>
@endsection

@section('content')
    <form method="GET" action="{{ route('app.assets.transfers') }}" id="list-filter"></form>

    <x-data-table :title="__('asset.pending_transfers')" icon="cil-transfer"
                  :paginator="$transfers" :searchable="false">
        <thead>
            <tr>
                <th class="col-index">{{ __('common.row_number') }}</th>
                <th>{{ __('asset.transfer_number') }}</th>
                <th>{{ __('asset.asset') }}</th>
                <th>{{ __('asset.from') }}</th>
                <th>{{ __('asset.to') }}</th>
                <th>{{ __('asset.status') }}</th>
                <th class="text-end">{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transfers as $index => $transfer)
                <tr>
                    <td class="col-index">{{ $transfers->firstItem() + $index }}</td>
                    <td><code>{{ $transfer->transfer_number }}</code></td>
                    <td>
                        <a href="{{ route('app.assets.show', $transfer->asset_id) }}" class="fw-semibold">
                            {{ $transfer->asset?->asset_code }}
                        </a>
                        <div class="text-body-secondary">{{ $transfer->asset?->name }}</div>
                    </td>
                    <td>{{ $transfer->fromFactory?->name }}</td>
                    <td>{{ $transfer->toFactory?->name }} / {{ $transfer->toLocation?->name }}</td>
                    <td>
                        <x-status-pill :status="$transfer->status" tone="info">
                            {{ __('asset.transfer_status_'.strtolower($transfer->status)) }}
                        </x-status-pill>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            @if ($transfer->status === 'REQUESTED')
                                <form method="POST" action="{{ route('app.transfers.approve', $transfer) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">{{ __('asset.approve') }}</button>
                                </form>
                            @endif

                            @if (in_array($transfer->status, ['REQUESTED', 'APPROVED', 'IN_TRANSIT'], true))
                                {{-- Receiving is the point at which the asset
                                     actually moves, so it is confirmed. --}}
                                <form method="POST" action="{{ route('app.transfers.receive', $transfer) }}"
                                      data-confirm="{{ __('asset.transfer_received', ['number' => $transfer->transfer_number]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">{{ __('asset.receive') }}</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-0">
                    <x-empty-state :title="__('asset.no_pending_transfers')"
                                   :description="__('asset.no_pending_transfers_hint')" />
                </td></tr>
            @endforelse
        </tbody>
    </x-data-table>
@endsection
