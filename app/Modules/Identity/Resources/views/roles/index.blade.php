@extends('layouts.app')
@section('title', __('user.roles'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.settings.users') }}">{{ __('user.users') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('user.roles') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('user.roles')" :subtitle="__('user.roles_intro')">
        <x-slot:actions>
            <a href="{{ route('app.settings.users') }}" class="btn btn-sm btn-outline-secondary">
                <i class="cil-arrow-left" aria-hidden="true"></i> {{ __('common.back') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="row">
        @foreach ($roles as $role)
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            <span class="fw-semibold">{{ $role->name }}</span>
                            <span class="badge bg-secondary ms-1">
                                {{ $role->scope === 'FACTORY' ? __('user.factory_scope') : __('user.company_scope') }}
                            </span>
                        </span>
                        <span class="badge bg-info text-white rounded-pill">
                            {{ trans_choice('user.holder_count', $assigned[$role->id] ?? 0, ['count' => $assigned[$role->id] ?? 0]) }}
                        </span>
                    </div>

                    <div class="card-body">
                        @if ($role->description)
                            <p class="small text-body-secondary">{{ $role->description }}</p>
                        @endif

                        <details>
                            <summary class="small">
                                {{ trans_choice('user.permission_count', $role->permissions->count(), ['count' => $role->permissions->count()]) }}
                            </summary>

                            <ul class="small mt-2 mb-0 ps-3">
                                @foreach ($role->permissions->sortBy('code') as $permission)
                                    <li><code>{{ $permission->code }}</code></li>
                                @endforeach
                            </ul>
                        </details>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Seeded roles are not editable: a tenant clones one to change it. That
         screen comes next; this one answers the question an administrator has
         while handing roles out. --}}
    <div class="alert alert-secondary">{{ __('user.roles_read_only') }}</div>
@endsection
