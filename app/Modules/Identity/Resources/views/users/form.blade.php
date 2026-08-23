@extends('layouts.app')
@section('title', $user ? $user->name : __('user.new_user'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.settings.users') }}">{{ __('user.users') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $user ? $user->name : __('user.new_user') }}</li>
@endsection

@section('content')
    <x-page-header :title="$user ? __('user.edit_user') : __('user.new_user')" :subtitle="$user?->email">
        <x-slot:actions>
            <a href="{{ route('app.settings.users') }}" class="btn btn-sm btn-outline-secondary">
                <i class="cil-arrow-left" aria-hidden="true"></i> {{ __('common.back') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST"
          action="{{ $user ? route('app.settings.users.update', $user) : route('app.settings.users.store') }}">
        @csrf
        @if ($user)
            @method('PATCH')
        @endif

        <div class="row">
            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-user" aria-hidden="true"></i>
                        <span>{{ __('user.person') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('user.name') }}</label>
                            <input id="name" name="name" type="text" class="form-control"
                                   value="{{ old('name', $user?->name) }}" required maxlength="255">
                            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('user.email') }}</label>
                            @if ($user)
                                {{-- The address identifies the account across every
                                     company it belongs to, so it is not this
                                     company's to change. --}}
                                <input id="email" type="email" class="form-control" value="{{ $user->email }}" disabled>
                                <div class="form-text">{{ __('user.email_is_the_account') }}</div>
                            @else
                                <input id="email" name="email" type="email" class="form-control"
                                       value="{{ old('email') }}" required maxlength="255">
                            @endif
                            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label">{{ __('user.phone') }}</label>
                            <input id="phone" name="phone" type="text" class="form-control"
                                   value="{{ old('phone', $user?->phone) }}" maxlength="32">
                        </div>

                        <div class="mb-3">
                            <label for="locale" class="form-label">{{ __('user.language') }}</label>
                            <select id="locale" name="locale" class="form-select">
                                <option value="bn" @selected(old('locale', $user?->locale ?? 'bn') === 'bn')>বাংলা</option>
                                <option value="en" @selected(old('locale', $user?->locale) === 'en')>English</option>
                            </select>
                        </div>

                        @unless ($user)
                            <div class="alert alert-secondary small mb-0">
                                {{-- Most people on a factory floor have no working
                                     email address, so a reset link is not a path
                                     everybody can take. --}}
                                {{ __('user.password_will_be_generated') }}
                            </div>
                        @endunless
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-shield-alt" aria-hidden="true"></i>
                        <span>{{ __('user.roles') }}</span>
                    </div>
                    <div class="card-body">
                        @error('roles')<div class="alert alert-danger">{{ $message }}</div>@enderror

                        <div class="mb-3">
                            <label for="factory_id" class="form-label">{{ __('user.factory') }}</label>
                            <select id="factory_id" name="factory_id" class="form-select">
                                <option value="">{{ __('user.company_wide') }}</option>
                                @foreach ($factories as $factory)
                                    <option value="{{ $factory->id }}"
                                            @selected(old('factory_id', $assignedFactoryId) === $factory->id)>
                                        {{ $factory->name }}
                                    </option>
                                @endforeach
                            </select>
                            {{-- Only the factory-scoped roles use it; a company role
                                 covers every factory whatever is chosen here. --}}
                            <div class="form-text">{{ __('user.factory_hint') }}</div>
                            @error('factory_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-2">
                            @foreach ($roles as $role)
                                <div class="col-md-6">
                                    <div class="form-check border rounded p-2 ps-4 h-100">
                                        <input class="form-check-input" type="checkbox" name="roles[]"
                                               id="role-{{ $role->id }}" value="{{ $role->id }}"
                                               @checked(in_array($role->id, old('roles', $assignedRoleIds), true))>
                                        <label class="form-check-label d-block" for="role-{{ $role->id }}">
                                            <span class="fw-semibold">{{ $role->name }}</span>
                                            <span class="badge bg-secondary ms-1">
                                                {{ $role->scope === 'FACTORY' ? __('user.factory_scope') : __('user.company_scope') }}
                                            </span>
                                            <span class="d-block small text-body-secondary">
                                                {{ trans_choice('user.permission_count', $role->permissions->count(), ['count' => $role->permissions->count()]) }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-info text-white">{{ __('common.save') }}</button>
            <a href="{{ route('app.settings.users') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
