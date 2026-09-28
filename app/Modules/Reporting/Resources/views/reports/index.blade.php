@extends('layouts.app')
@section('title', __('report.reports'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('report.reports') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('report.reports')">
        <x-slot:actions>
            <a href="{{ route('app.reports.jobs') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('report.generated_reports') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($groups->isEmpty())
        {{-- Reports are gated on the data behind them, not on a blanket
             reporting permission, so an empty list is a real answer rather
             than a mistake. --}}
        <x-empty-state :title="__('report.no_reports')" :description="__('report.no_reports_hint')" />
    @endif

    @foreach ($groups as $group => $reports)
        <h2 class="h6 text-body-secondary text-uppercase mb-2">{{ __('report.groups.'.$group) }}</h2>

        <div class="row">
            @foreach ($reports as $report)
                <div class="col-md-6 col-xl-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="h6 mb-1">
                                <a href="{{ route('app.reports.show', $report->key()) }}" class="stretched-link">
                                    {{ $report->title() }}
                                </a>
                            </h3>
                            <p class="small text-body-secondary mb-0">{{ $report->description() }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endsection
