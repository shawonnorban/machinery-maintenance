@extends('layouts.app')
@section('title', $report->title())

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.reports.index') }}">{{ __('report.reports') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $report->title() }}</li>
@endsection

@section('content')
    <x-page-header :title="$report->title()" :subtitle="$report->description()">
        <x-slot:actions>
            <a href="{{ route('app.reports.index') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('report.back_to_reports') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4">
        <div class="card-header">
            <i class="cil-filter" aria-hidden="true"></i>
            <span>{{ __('report.filters') }}</span>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('app.reports.show', $report->key()) }}" class="row g-3">
                @if (in_array('period', $report->filters(), true))
                    <div class="col-sm-6 col-lg-3">
                        <label for="from" class="form-label">{{ __('report.from') }}</label>
                        <input type="date" class="form-control" id="from" name="from"
                               value="{{ request('from', $query->from->timezone(app('App\Shared\Support\TenantTimezone')->current())->format('Y-m-d')) }}">
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <label for="to" class="form-label">{{ __('report.to') }}</label>
                        <input type="date" class="form-control" id="to" name="to"
                               value="{{ request('to', $query->to->timezone(app('App\Shared\Support\TenantTimezone')->current())->format('Y-m-d')) }}">
                    </div>
                @endif

                @if (in_array('factory', $report->filters(), true))
                    <div class="col-sm-6 col-lg-3">
                        <label for="factory_id" class="form-label">{{ __('report.factory') }}</label>
                        <select class="form-select" id="factory_id" name="factory_id"
                                @disabled(session('factory_scope_id'))>
                            <option value="">{{ __('report.all_factories') }}</option>
                            @foreach ($factories as $factory)
                                <option value="{{ $factory->id }}" @selected($query->factoryId === $factory->id)>
                                    {{ $factory->name }}
                                </option>
                            @endforeach
                        </select>

                        @if (session('factory_scope_id'))
                            {{-- The header scope narrows every screen; a report
                                 must not be able to widen it (Frontend 4.2). --}}
                            <div class="form-text">{{ __('report.scoped_by_header') }}</div>
                        @endif
                    </div>
                @endif

                @if (in_array('asset', $report->filters(), true))
                    <div class="col-sm-6 col-lg-3">
                        <label for="asset_id" class="form-label">{{ __('report.asset') }}</label>
                        <select class="form-select" id="asset_id" name="asset_id" data-tom-select>
                            <option value="">{{ __('report.all_assets') }}</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}" @selected($query->assetId === $asset->id)>
                                    {{ $asset->asset_code }} — {{ $asset->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if (in_array('status', $report->filters(), true))
                    <div class="col-sm-6 col-lg-3">
                        <label for="status" class="form-label">{{ __('report.status') }}</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">{{ __('report.all_statuses') }}</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($query->get('status') === $status)>
                                    {{ __('asset.status_'.strtolower($status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-sm btn-info text-white">{{ __('report.run') }}</button>
                </div>
            </form>
        </div>

        <div class="card-footer d-flex flex-wrap gap-3 small text-body-secondary">
            @foreach ($meta as $label => $value)
                <span><strong>{{ $label }}:</strong> {{ $value }}</span>
            @endforeach
        </div>
    </div>

    @can('report.report.export')
        <form method="POST" action="{{ route('app.reports.export', $report->key()) }}" class="mb-4">
            @csrf

            @foreach (request()->only(['from', 'to', 'factory_id', 'asset_id', 'status']) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach

            <div class="d-flex align-items-center gap-2 flex-wrap">
                @foreach ($formats as $format)
                    <button class="btn btn-sm btn-outline-primary" name="format" value="{{ $format }}">
                        {{ __('report.export') }} · {{ $format }}
                    </button>
                @endforeach

                @if ($willQueue)
                    {{-- Said before the click, not after: a person who expects a
                         download and gets a redirect assumes it failed. --}}
                    <span class="small text-body-secondary">{{ __('report.will_queue') }}</span>
                @endif
            </div>
        </form>
    @endcan

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead>
                    <tr>
                        @foreach ($columns as $key => $column)
                            <th class="{{ ($column['numeric'] ?? false) ? 'text-end' : '' }}">
                                {{ __($column['label']) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($columns as $key => $column)
                                <td class="{{ ($column['numeric'] ?? false) ? 'text-end' : '' }}">
                                    @if (($row[$key] ?? null) === null)
                                        {{-- Empty, not "0": a KPI with no
                                             denominator has no value, and a
                                             zero in a report gets quoted. --}}
                                        <span class="text-body-secondary">—</span>
                                    @else
                                        {{ $row[$key] }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="text-body-secondary">
                                {{ __('report.no_rows') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($truncated)
            <div class="card-footer small text-body-secondary">
                {{ __('report.preview_note', ['count' => $previewLimit]) }}
            </div>
        @endif
    </div>
@endsection
