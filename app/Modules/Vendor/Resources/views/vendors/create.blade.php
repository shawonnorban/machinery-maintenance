@extends('layouts.app')
@section('title', __('vendor.new_vendor'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.vendors.index') }}">{{ __('vendor.vendors') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('vendor.new_vendor') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('vendor.new_vendor')" />

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('app.vendors.store') }}">
                @include('vendor::vendors._form')

                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-info text-white">{{ __('common.save') }}</button>
                    <a href="{{ route('app.vendors.index') }}" class="btn btn-outline-secondary">
                        {{ __('common.cancel') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
