@extends('layouts.app')
@section('title', __('asset.pending_transfers'))

@section('content')
    <x-page-header :title="__('asset.pending_transfers')" />

    <div class="card">
        <div class="card-body p-0">
            @if ($transfers->isEmpty())
                <x-empty-state :title="__('asset.no_pending_transfers')"
                               :description="__('asset.no_pending_transfers_hint')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('asset.transfer_number') }}</th>
                                <th>{{ __('asset.asset') }}</th>
                                <th>{{ __('asset.from') }}</th>
                                <th>{{ __('asset.to') }}</th>
                                <th>{{ __('asset.status') }}</th>
                                <th class="text-end">{{ __('asset.filter') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transfers as $transfer)
                                <tr>
                                    <td><code>{{ $transfer->transfer_number }}</code></td>
                                    <td>
                                        <a href="{{ route('app.assets.show', $transfer->asset_id) }}">
                                            {{ $transfer->asset?->asset_code }}
                                        </a>
                                        <div class="small text-body-secondary">{{ $transfer->asset?->name }}</div>
                                    </td>
                                    <td class="small">{{ $transfer->fromFactory?->name }}</td>
                                    <td class="small">
                                        {{ $transfer->toFactory?->name }} / {{ $transfer->toLocation?->name }}
                                    </td>
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
                                                <form method="POST" action="{{ route('app.transfers.receive', $transfer) }}"
                                                      data-confirm="{{ __('asset.transfer_received', ['number' => $transfer->transfer_number]) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-primary">{{ __('asset.receive') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($transfers->hasPages())
            <div class="card-footer">{{ $transfers->links() }}</div>
        @endif
    </div>
@endsection
