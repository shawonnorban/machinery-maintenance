@extends('layouts.app')
@section('title', __('import.review'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.imports.index') }}">{{ __('import.imports') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $importer->title() }}</li>
@endsection

@section('content')
    <x-page-header :title="$importer->title()" :subtitle="$job->original_name">
        <x-slot:actions>
            <a href="{{ route('app.imports.index') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('import.back_to_imports') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        <div class="col-sm-6 col-xl-3">
            <x-kpi-tile :label="__('import.stats.total')" :value="number_format($job->total_rows)" tone="primary" />
        </div>

        <div class="col-sm-6 col-xl-3">
            <x-kpi-tile :label="__('import.stats.valid')"
                        :value="number_format($job->valid_rows)"
                        :tone="$job->valid_rows > 0 ? 'success' : 'secondary'" />
        </div>

        <div class="col-sm-6 col-xl-3">
            {{-- Beside the ready count, never below the fold: a person who
                 confirms without seeing this is a person who finds out about
                 the four bad rows a month later. --}}
            <x-kpi-tile :label="__('import.stats.failed')"
                        :value="number_format($job->failed_rows)"
                        :tone="$job->failed_rows > 0 ? 'danger' : 'success'" />
        </div>

        <div class="col-sm-6 col-xl-3">
            <x-kpi-tile :label="__('report.job.status')"
                        :value="__('import.statuses.'.$job->status)"
                        :tone="match ($job->status) {
                            'COMPLETED' => 'success',
                            'FAILED' => 'danger',
                            'CANCELLED' => 'secondary',
                            default => 'info',
                        }" />
        </div>
    </div>

    @if ($job->status === 'COMPLETED')
        <div class="alert alert-success">
            {{ __('import.stats.created') }}: <strong>{{ number_format($job->success_rows) }}</strong> ·
            {{ __('import.stats.updated') }}: <strong>{{ number_format($job->updated_rows) }}</strong>
        </div>
    @endif

    @if ($job->error_message)
        <div class="alert alert-danger">{{ $job->error_message }}</div>
    @endif

    @if ($job->isConfirmable())
        <form method="POST" action="{{ route('app.imports.confirm', $job) }}" class="mb-4 d-flex gap-2 flex-wrap">
            @csrf

            <button class="btn btn-info text-white">
                {{ __('import.confirm', ['count' => number_format($job->valid_rows)]) }}
            </button>

            <span class="small text-body-secondary align-self-center">{{ __('import.confirm_hint') }}</span>
        </form>
    @elseif ($job->status === 'VALIDATED')
        <div class="alert alert-warning">{{ __('import.nothing_valid') }}</div>
    @endif

    @if (in_array($job->status, ['UPLOADED', 'VALIDATED'], true))
        <form method="POST" action="{{ route('app.imports.cancel', $job) }}" class="mb-4">
            @csrf
            <button class="btn btn-sm btn-outline-danger">{{ __('import.cancel') }}</button>
        </form>
    @endif

    @if ($importErrors->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header">
                <i class="cil-warning" aria-hidden="true"></i>
                <span>{{ __('import.errors_title') }}</span>

                <a href="{{ route('app.imports.errors', $job) }}" class="btn btn-sm btn-outline-danger ms-auto">
                    {{ __('import.download_errors') }}
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('import.error_columns.row') }}</th>
                            <th>{{ __('import.error_columns.column') }}</th>
                            <th>{{ __('import.error_columns.value') }}</th>
                            <th>{{ __('import.error_columns.error') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($importErrors as $error)
                            <tr>
                                <td>{{ $error->row_number }}</td>
                                <td><code>{{ $error->field }}</code></td>
                                <td>{{ $error->value }}</td>
                                <td>{{ $error->error }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($errorCount > $importErrors->count())
                <div class="card-footer small text-body-secondary">
                    {{ __('import.errors_truncated', ['count' => $importErrors->count(), 'total' => $errorCount]) }}
                </div>
            @endif
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="cil-magnifying-glass" aria-hidden="true"></i>
            <span>{{ __('import.preview') }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('import.error_columns.row') }}</th>
                        @foreach ($columns as $name => $column)
                            <th>{{ __($column->label) }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ $row->isValid() ? '' : 'table-danger' }}">
                            <td>{{ $row->rowNumber }}</td>

                            @foreach ($columns as $name => $column)
                                <td>{{ $row->original[$name] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer small text-body-secondary">
            {{ __('import.preview_hint', ['count' => count($rows)]) }}
        </div>
    </div>
@endsection
