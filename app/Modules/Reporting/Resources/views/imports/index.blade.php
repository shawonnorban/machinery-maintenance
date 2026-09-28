@extends('layouts.app')
@section('title', __('import.imports'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('import.imports') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('import.imports')" />

    @if ($importers->isEmpty())
        <x-empty-state :title="__('import.no_importers')" :description="__('import.no_importers_hint')" />
    @else
        {{-- Order matters and nothing in the code can enforce it across two
             separate uploads, so it is said plainly here. --}}
        <div class="alert alert-info">{{ __('import.order_hint') }}</div>

        <div class="row">
            @foreach ($importers as $importer)
                <div class="col-md-6 col-xl-3">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h2 class="h6 mb-1">
                                <a href="{{ route('app.imports.show', $importer->type()) }}" class="stretched-link">
                                    {{ $importer->title() }}
                                </a>
                            </h2>
                            <p class="small text-body-secondary mb-0">{{ $importer->description() }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">
            <i class="cil-history" aria-hidden="true"></i>
            <span>{{ __('import.recent') }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('import.import') }}</th>
                        <th>{{ __('report.job.requested_at') }}</th>
                        <th class="text-end">{{ __('import.stats.total') }}</th>
                        <th class="text-end">{{ __('import.stats.created') }}</th>
                        <th class="text-end">{{ __('import.stats.updated') }}</th>
                        <th class="text-end">{{ __('import.stats.failed') }}</th>
                        <th>{{ __('report.job.status') }}</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td>
                                {{ $registry->has($job->type) ? $registry->find($job->type)->title() : $job->type }}
                                <div class="small text-body-secondary">{{ $job->original_name }}</div>
                            </td>
                            <td>@dt($job->created_at)</td>
                            <td class="text-end">{{ number_format($job->total_rows) }}</td>
                            <td class="text-end">{{ number_format($job->success_rows) }}</td>
                            <td class="text-end">{{ number_format($job->updated_rows) }}</td>
                            <td class="text-end {{ $job->failed_rows > 0 ? 'text-danger' : '' }}">
                                {{ number_format($job->failed_rows) }}
                            </td>
                            <td>
                                <x-status-pill :status="$job->status" :tone="match ($job->status) {
                                    'COMPLETED' => 'success',
                                    'FAILED' => 'danger',
                                    'CANCELLED' => 'secondary',
                                    'IMPORTING', 'VALIDATING' => 'info',
                                    default => 'warning',
                                }">
                                    {{ __('import.statuses.'.$job->status) }}
                                </x-status-pill>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('app.imports.review', $job) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    {{ __('import.review') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-body-secondary">{{ __('import.no_recent') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
