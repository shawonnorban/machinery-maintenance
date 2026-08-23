@extends('layouts.app')
@section('title', __('masterdata.master_data'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('masterdata.master_data') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('masterdata.master_data')" :subtitle="__('masterdata.intro')" />

    <div class="row">
        @foreach ($grouped as $group => $types)
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">{{ __('masterdata.groups.'.$group) }}</div>

                    <div class="list-group list-group-flush">
                        @foreach ($types as $type)
                            <a href="{{ route('app.settings.master-data.show', $type->key()) }}"
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
                                <span>
                                    <span class="fw-semibold">{{ $type->title() }}</span>
                                    <span class="d-block small text-body-secondary">{{ $type->description() }}</span>
                                </span>
                                <span class="badge bg-secondary rounded-pill">{{ $counts[$type->key()] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
