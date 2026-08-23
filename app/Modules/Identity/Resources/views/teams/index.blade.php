@extends('layouts.app')
@section('title', __('team.teams'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('team.teams') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('team.teams')" :subtitle="__('team.intro')" />

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('team.name') }}</th>
                        <th>{{ __('team.factory') }}</th>
                        <th>{{ __('team.specialization') }}</th>
                        <th>{{ __('settings.status') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($teams as $team)
                        <tr @class(['opacity-50' => $team->status !== 'ACTIVE'])>
                            <td>
                                <span class="fw-semibold">{{ $team->name }}</span>
                                <div class="small text-body-secondary">{{ $team->code }}</div>
                            </td>
                            <td>{{ $team->factory?->name }}</td>
                            <td class="small">{{ $team->specialization }}</td>
                            <td>
                                <x-status-pill :status="$team->status"
                                               :tone="$team->status === 'ACTIVE' ? 'success' : 'secondary'">
                                    {{ $team->status === 'ACTIVE' ? __('team.active') : __('team.inactive') }}
                                </x-status-pill>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <form method="POST" action="{{ route('app.teams.toggle', $team) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            {{ $team->status === 'ACTIVE' ? __('team.deactivate') : __('team.activate') }}
                                        </button>
                                    </form>

                                    {{-- Only a team nothing was ever handed to: a job
                                         still has to be able to say who it went to. --}}
                                    <form method="POST" action="{{ route('app.teams.destroy', $team) }}"
                                          onsubmit="return confirm(@js(__('team.delete_confirm', ['name' => $team->name])))">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('common.delete') }}"
                                                aria-label="{{ __('common.delete') }}">
                                            <i class="cil-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state :title="__('team.no_teams')" :description="__('team.no_teams_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('team.new_team') }}</div>

        <div class="card-body">
            <form method="POST" action="{{ route('app.teams.store') }}" class="row g-2 align-items-end">
                @csrf

                <div class="col-md-4">
                    <label for="name" class="form-label mb-1">{{ __('team.name') }}</label>
                    <input id="name" name="name" type="text" class="form-control form-control-sm"
                           value="{{ old('name') }}" required maxlength="255">
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-2">
                    <label for="code" class="form-label mb-1">{{ __('team.code') }}</label>
                    <input id="code" name="code" type="text" class="form-control form-control-sm text-uppercase"
                           value="{{ old('code') }}" required maxlength="32">
                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="factory_id" class="form-label mb-1">{{ __('team.factory') }}</label>
                    <select id="factory_id" name="factory_id" class="form-select form-select-sm" required>
                        <option value="">—</option>
                        @foreach ($factories as $factory)
                            <option value="{{ $factory->id }}" @selected(old('factory_id') === $factory->id)>
                                {{ $factory->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('factory_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="specialization" class="form-label mb-1">{{ __('team.specialization') }}</label>
                    <input id="specialization" name="specialization" type="text" class="form-control form-control-sm"
                           value="{{ old('specialization') }}" maxlength="255"
                           placeholder="{{ __('team.specialization_example') }}">
                </div>

                <div class="col-12">
                    <button class="btn btn-sm btn-info text-white">{{ __('team.add_team') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
