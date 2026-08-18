@extends('layouts.app')
@section('title', __('maintenance.edit_plan'))

@section('content')
    <x-page-header :title="__('maintenance.edit_plan')" :subtitle="$plan->name" />

    <form method="POST" action="{{ route('app.maintenance.plans.update', $plan) }}">
        @csrf
        @method('PATCH')
        @include('maintenance::plans._form', ['plan' => $plan])

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">{{ __('maintenance.edit_plan') }}</button>
            <a href="{{ route('app.maintenance.plans.show', $plan) }}" class="btn btn-outline-secondary">{{ __('asset.clear') }}</a>
        </div>
    </form>
@endsection
