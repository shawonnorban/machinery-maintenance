@extends('layouts.app')
@section('title', $technician ? $technician->name : __('technician.new_technician'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('app.technicians.index') }}">{{ __('technician.technicians') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ $technician ? $technician->name : __('technician.new_technician') }}
    </li>
@endsection

@section('content')
    <x-page-header :title="$technician ? __('technician.edit_technician') : __('technician.new_technician')">
        <x-slot:actions>
            <a href="{{ route('app.technicians.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="cil-arrow-left" aria-hidden="true"></i> {{ __('common.back') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST"
          action="{{ $technician
              ? route('app.technicians.update', $technician)
              : route('app.technicians.store') }}">
        @csrf
        @if ($technician)
            @method('PATCH')
        @endif

        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-user" aria-hidden="true"></i>
                        <span>{{ __('technician.person') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="name" class="form-label">{{ __('technician.name') }}</label>
                                <input id="name" name="name" type="text" class="form-control"
                                       value="{{ old('name', $technician?->name) }}" required maxlength="255">
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-5">
                                <label for="employee_id" class="form-label">{{ __('technician.employee_id') }}</label>
                                <input id="employee_id" name="employee_id" type="text" class="form-control"
                                       value="{{ old('employee_id', $technician?->employee_id) }}" required maxlength="64">
                                @error('employee_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">{{ __('technician.phone') }}</label>
                                <input id="phone" name="phone" type="text" class="form-control"
                                       value="{{ old('phone', $technician?->phone) }}" maxlength="32">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">{{ __('technician.email') }}</label>
                                <input id="email" name="email" type="email" class="form-control"
                                       value="{{ old('email', $technician?->email) }}" maxlength="255">
                            </div>

                            <div class="col-md-6">
                                <label for="joining_date" class="form-label">{{ __('technician.joining_date') }}</label>
                                <input id="joining_date" name="joining_date" type="date" class="form-control"
                                       value="{{ old('joining_date', $technician?->joining_date?->toDateString()) }}">
                            </div>

                            <div class="col-md-6">
                                <label for="user_id" class="form-label">{{ __('technician.login') }}</label>
                                <select id="user_id" name="user_id" class="form-select">
                                    <option value="">{{ __('technician.no_login') }}</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}"
                                                @selected(old('user_id', $technician?->user_id) === $user->id)>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                                {{-- A technician may exist without an account: a
                                     supervisor records their work for them. --}}
                                <div class="form-text">{{ __('technician.login_hint') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-sitemap" aria-hidden="true"></i>
                        <span>{{ __('technician.covers') }}</span>
                    </div>
                    <div class="card-body">
                        {{-- A dyeing technician covers the dye house, a sewing
                             mechanic the sewing floor. This decides who the
                             assignment screen offers first, never who may be
                             assigned (ADR-065). --}}
                        <p class="small text-body-secondary">{{ __('technician.covers_hint') }}</p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="factory_id" class="form-label">{{ __('technician.factory') }}</label>
                                <select id="factory_id" name="factory_id" class="form-select" required>
                                    <option value="">—</option>
                                    @foreach ($factories as $factory)
                                        <option value="{{ $factory->id }}"
                                                @selected(old('factory_id', $technician?->factory_id) === $factory->id)>
                                            {{ $factory->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('factory_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="department_id" class="form-label">{{ __('technician.department') }}</label>
                                <select id="department_id" name="department_id" class="form-select"
                                        @disabled($departments->isEmpty())>
                                    <option value="">{{ __('technician.whole_factory') }}</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}"
                                                @selected(old('department_id', $technician?->department_id) === $department->id)>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="production_line_id" class="form-label">
                                    {{ __('technician.production_line') }}
                                </label>
                                <select id="production_line_id" name="production_line_id" class="form-select"
                                        @disabled($lines->isEmpty())>
                                    <option value="">{{ __('technician.whole_department') }}</option>
                                    @foreach ($lines as $line)
                                        <option value="{{ $line->id }}"
                                                @selected(old('production_line_id', $technician?->production_line_id) === $line->id)>
                                            {{ $line->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('production_line_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="max_concurrent_work_orders" class="form-label">
                                    {{ __('technician.workload_limit') }}
                                </label>
                                <input id="max_concurrent_work_orders" name="max_concurrent_work_orders"
                                       type="number" min="1" max="50" class="form-control"
                                       value="{{ old('max_concurrent_work_orders', $technician?->max_concurrent_work_orders) }}">
                                <div class="form-text">{{ __('technician.workload_hint') }}</div>
                            </div>

                            <div class="col-12">
                                <label for="specialization" class="form-label">
                                    {{ __('technician.specialization') }}
                                </label>
                                <input id="specialization" name="specialization" type="text" class="form-control"
                                       value="{{ old('specialization', $technician?->specialization) }}"
                                       maxlength="255" placeholder="{{ __('technician.specialization_example') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-info text-white">{{ __('common.save') }}</button>
            <a href="{{ route('app.technicians.index') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
