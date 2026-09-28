@extends('layouts.app')
@section('title', __('report.generated_reports'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.reports.index') }}">{{ __('report.reports') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('report.generated_reports') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('report.generated_reports')">
        <x-slot:actions>
            <a href="{{ route('app.reports.index') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('report.back_to_reports') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('report.report') }}</th>
                        <th>{{ __('report.job.format') }}</th>
                        <th>{{ __('report.job.requested_at') }}</th>
                        <th class="text-end">{{ __('report.job.rows') }}</th>
                        <th>{{ __('report.job.status') }}</th>
                        <th>{{ __('report.job.expires') }}</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td>
                                {{ $registry->has($job->report_type)
                                    ? $registry->find($job->report_type)->title()
                                    : $job->report_type }}

                                @if ($job->status === 'FAILED' && $job->error_message)
                                    {{-- The reason lives on the row, because a
                                         queue worker's log is not somewhere the
                                         person who asked can look. --}}
                                    <div class="small text-danger">{{ $job->error_message }}</div>
                                @endif
                            </td>
                            <td>{{ $job->format }}</td>
                            <td>@dt($job->created_at)</td>
                            <td class="text-end">{{ $job->row_count === null ? '—' : number_format($job->row_count) }}</td>
                            <td>
                                <x-status-pill :status="$job->status" :tone="match ($job->status) {
                                    'COMPLETED' => 'success',
                                    'FAILED' => 'danger',
                                    'RUNNING' => 'info',
                                    'EXPIRED' => 'secondary',
                                    default => 'warning',
                                }">
                                    {{ __('report.statuses.'.$job->status) }}
                                </x-status-pill>
                            </td>
                            <td>@dt($job->expires_at)</td>
                            <td class="text-end">
                                @if ($job->isDownloadable())
                                    <a href="{{ route('app.reports.jobs.download', $job) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        {{ __('report.job.download') }}
                                    </a>
                                @elseif ($job->status === 'COMPLETED')
                                    <span class="small text-body-secondary">{{ __('report.job.expired') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty-state :title="__('report.job.none')"
                                               :description="__('report.job.none_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer small text-body-secondary">
            {{ __('report.job.retention', ['days' => App\Modules\Reporting\Actions\RequestReport::RETENTION_DAYS]) }}
        </div>
    </div>

    {{ $jobs->links() }}
@endsection
