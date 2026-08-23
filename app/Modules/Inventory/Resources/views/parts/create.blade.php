@extends('layouts.app')
@section('title', __('inventory.new_part'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.inventory.parts') }}">{{ __('inventory.spare_parts') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('inventory.new_part') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('inventory.new_part')" />

    <form method="POST" action="{{ route('app.inventory.parts.store') }}">
        @csrf

        @include('inventory::parts._form', ['part' => null, 'categories' => $categories])

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-info text-white">{{ __('inventory.new_part') }}</button>
            <a href="{{ route('app.inventory.parts') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
