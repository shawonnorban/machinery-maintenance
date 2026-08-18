@extends('layouts.app')
@section('title', __('nav.dashboard'))

@section('content')
    <x-page-header :title="__('nav.dashboard')"
                   :subtitle="$company?->name" />

    {{-- KPI tiles render N/A until the Analytics module lands (build order 18).
         Showing 0 would be a lie a manager might act on. --}}
    <div class="row">
        <div class="col-sm-6 col-lg-3">
            <x-kpi-tile :label="__('dash.total_assets')" :value="number_format($assetCount)"
                        :caption="__('dash.registered')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-kpi-tile :label="__('dash.availability')" :value="null"
                        :reason="__('dash.needs_downtime_module')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-kpi-tile :label="__('dash.mtbf')" :value="null"
                        :reason="__('dash.needs_downtime_module')" />
        </div>
        <div class="col-sm-6 col-lg-3">
            <x-kpi-tile :label="__('dash.open_work_orders')" :value="null"
                        :reason="__('dash.needs_work_order_module')" />
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">{{ __('dash.factories') }}</div>
                <div class="card-body p-0">
                    @if ($factories->isEmpty())
                        <x-empty-state :title="__('dash.no_factories')"
                                       :description="__('dash.no_factories_hint')" />
                    @else
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('dash.factory') }}</th>
                                    <th>{{ __('dash.code') }}</th>
                                    <th>{{ __('dash.timezone') }}</th>
                                    <th>{{ __('dash.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($factories as $factory)
                                    <tr>
                                        <td>{{ $factory->name }}</td>
                                        <td><code>{{ $factory->code }}</code></td>
                                        <td class="small text-body-secondary">{{ $factory->timezone }}</td>
                                        <td>
                                            <x-status-pill :status="$factory->status"
                                                           :tone="$factory->status === 'ACTIVE' ? 'success' : 'secondary'" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">{{ __('dash.your_access') }}</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('dash.signed_in_as') }}</dt>
                        <dd class="col-7">{{ auth()->user()->name }}</dd>

                        <dt class="col-5">{{ __('dash.company') }}</dt>
                        <dd class="col-7" data-testid="company-id">{{ $company?->name }}</dd>

                        <dt class="col-5">{{ __('dash.accessible_factories') }}</dt>
                        <dd class="col-7" data-testid="factory-count">{{ $factories->count() }}</dd>

                        <dt class="col-5">{{ __('dash.permissions') }}</dt>
                        <dd class="col-7">{{ $permissionCount }}</dd>
                    </dl>

                    <div class="d-flex gap-2 mt-3">
                        @can('asset.asset.create')
                            <button class="btn btn-sm btn-primary" data-testid="create-asset">
                                {{ __('dash.create_asset') }}
                            </button>
                        @endcan

                        @can('billing.subscription.manage')
                            <button class="btn btn-sm btn-outline-secondary" data-testid="manage-billing">
                                {{ __('nav.billing') }}
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
