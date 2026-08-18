@extends('layouts.app')
@section('title', __('maintenance.new_plan'))

@section('content')
    <x-page-header :title="__('maintenance.new_plan')" />

    <form method="POST" action="{{ route('app.maintenance.plans.store') }}">
        @csrf
        @include('maintenance::plans._form')

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">{{ __('maintenance.new_plan') }}</button>
            <a href="{{ route('app.maintenance.plans') }}" class="btn btn-outline-secondary">{{ __('asset.clear') }}</a>
        </div>
    </form>
@endsection
