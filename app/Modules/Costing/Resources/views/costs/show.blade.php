@extends('layouts.app')
@section('title', __('cost.lifecycle'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.assets.index') }}">{{ __('asset.assets') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.assets.show', $asset) }}">{{ $asset->asset_code }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('cost.lifecycle') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('cost.lifecycle')" :subtitle="$asset->asset_code.' — '.$asset->name" />

    <div class="row">
        <div class="col-sm-3">
            <x-kpi-tile :label="__('cost.acquisition')"
                        :value="number_format((float) $summary['acquisition'], 0)" tone="secondary" />
        </div>
        <div class="col-sm-3">
            <x-kpi-tile :label="__('cost.total_spend')"
                        :value="number_format((float) $summary['total_spend'], 0)" tone="primary" />
        </div>
        <div class="col-sm-3">
            <x-kpi-tile :label="__('cost.lifetime_total')"
                        :value="number_format((float) $summary['lifetime_total'], 0)" tone="info" />
        </div>
        <div class="col-sm-3">
            {{-- The figure that turns "this keeps breaking" into a decision.
                 N/A rather than zero when there is no purchase price: a
                 percentage of an unknown is no answer at all. --}}
            <x-kpi-tile :label="__('cost.spend_against_value')"
                        :value="$spendRatio === null ? null : $spendRatio.'%'"
                        :reason="__('cost.no_purchase_price')"
                        :caption="__('cost.spend_against_value_hint')"
                        :tone="$spendRatio !== null && $spendRatio >= 50 ? 'danger' : 'success'" />
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-chart-pie" aria-hidden="true"></i>
                    <span>{{ __('cost.by_category') }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('cost.category') }}</th>
                                <th class="text-end">{{ __('cost.entries') }}</th>
                                <th class="text-end">{{ __('cost.amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($summary['by_category'] as $row)
                                <tr>
                                    <td>{{ $row->category?->label() ?? '—' }}</td>
                                    <td class="text-end text-body-secondary">{{ $row->entries }}</td>
                                    <td class="text-end">{{ number_format((float) $row->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-body-secondary">{{ __('cost.no_entries') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer small text-body-secondary">{{ __('cost.depreciation_note') }}</div>
            </div>

            @if ($canPost)
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-plus" aria-hidden="true"></i>
                        <span>{{ __('cost.post_cost') }}</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('app.costs.store') }}" class="row g-2">
                            @csrf
                            <input type="hidden" name="asset_id" value="{{ $asset->id }}">

                            <div class="col-12">
                                <label for="cost_category_id" class="form-label mb-1">{{ __('cost.category') }}</label>
                                <select id="cost_category_id" name="cost_category_id" class="form-select form-select-sm" required>
                                    @foreach ($categories as $category)
                                        {{-- Labour and parts are absent on purpose:
                                             they are derived from the work order and
                                             posting one by hand would charge the
                                             machine twice. --}}
                                        @unless (in_array($category->code, ['LABOR', 'PARTS'], true))
                                            <option value="{{ $category->id }}">{{ $category->label() }}</option>
                                        @endunless
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6">
                                <label for="cost_amount" class="form-label mb-1">{{ __('cost.amount') }}</label>
                                <input id="cost_amount" name="amount" type="number" step="0.0001"
                                       class="form-control form-control-sm" required>
                            </div>

                            <div class="col-3">
                                <label for="cost_currency" class="form-label mb-1">{{ __('cost.currency') }}</label>
                                <input id="cost_currency" name="currency" type="text" maxlength="3"
                                       class="form-control form-control-sm" value="{{ $asset->currency ?? 'BDT' }}" required>
                            </div>

                            <div class="col-3">
                                <label for="cost_rate" class="form-label mb-1">{{ __('cost.exchange_rate') }}</label>
                                <input id="cost_rate" name="exchange_rate" type="number" step="0.00000001" min="0.00000001"
                                       class="form-control form-control-sm" value="1">
                            </div>

                            <div class="col-6">
                                <label for="cost_source_type" class="form-label mb-1">{{ __('cost.source') }}</label>
                                <select id="cost_source_type" name="source_type" class="form-select form-select-sm">
                                    @foreach (['EXTERNAL_SERVICE', 'VENDOR', 'TRANSPORT', 'MANUAL'] as $source)
                                        <option value="{{ $source }}">{{ __('cost.source_'.strtolower($source)) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6">
                                <label for="cost_occurred_at" class="form-label mb-1">{{ __('cost.occurred_at') }}</label>
                                <input id="cost_occurred_at" name="occurred_at" type="datetime-local"
                                       class="form-control form-control-sm">
                            </div>

                            <div class="col-12">
                                <label for="cost_description" class="form-label mb-1">{{ __('cost.description') }}</label>
                                <input id="cost_description" name="description" type="text" maxlength="255"
                                       class="form-control form-control-sm">
                            </div>

                            <div class="col-12">
                                <label for="cost_invoice" class="form-label mb-1">{{ __('cost.invoice_reference') }}</label>
                                <input id="cost_invoice" name="invoice_reference" type="text" maxlength="255"
                                       class="form-control form-control-sm">
                            </div>

                            <div class="col-12">
                                <button class="btn btn-sm btn-info text-white">{{ __('cost.post_cost') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="cil-money" aria-hidden="true"></i>
                    <span>{{ __('cost.costs') }}</span>
                    <span class="ms-auto text-body-secondary">{{ $summary['entry_count'] }}</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('cost.occurred_at') }}</th>
                                <th>{{ __('cost.category') }}</th>
                                <th>{{ __('cost.source') }}</th>
                                <th>{{ __('cost.description') }}</th>
                                <th class="text-end">{{ __('cost.amount') }}</th>
                                <th class="text-end">{{ __('cost.base_amount') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr class="{{ $entry->is_reversal ? 'table-warning' : '' }}">
                                    <td>@dt($entry->occurred_at)</td>
                                    <td>{{ $entry->category?->label() }}</td>
                                    <td>
                                        {{ __('cost.source_'.strtolower($entry->source_type)) }}
                                        @if ($entry->isDerived())
                                            {{-- Written by the system from the records
                                                 underneath, so it cannot be edited here. --}}
                                            <div>
                                                <x-status-pill status="DERIVED" tone="secondary">
                                                    {{ __('cost.derived') }}
                                                </x-status-pill>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="small">
                                        {{ $entry->description }}
                                        @if ($entry->workOrder !== null)
                                            <div>
                                                <a href="{{ route('app.work-orders.show', $entry->work_order_id) }}">
                                                    {{ $entry->workOrder->work_order_number }}
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end {{ $entry->is_reversal ? 'text-danger' : '' }}">
                                        {{ $entry->amount }} {{ $entry->currency }}
                                    </td>
                                    <td class="text-end fw-semibold">{{ $entry->base_amount }}</td>
                                    <td class="text-end">
                                        @can('cost.entry.reverse')
                                            @if (! $entry->is_reversal && ! $entry->isDerived())
                                                <form method="POST" action="{{ route('app.costs.reverse', $entry->id) }}"
                                                      class="d-flex gap-1">
                                                    @csrf
                                                    <input name="reason" type="text" required maxlength="255"
                                                           class="form-control form-control-sm" style="width: 9rem"
                                                           placeholder="{{ __('cost.reason') }}">
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        {{ __('cost.reverse') }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="p-0">
                                    <x-empty-state :title="__('cost.no_entries')"
                                                   :description="__('cost.no_entries_hint')" />
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card-footer small text-body-secondary">{{ __('cost.reversal_hint') }}</div>
            </div>
        </div>
    </div>
@endsection
