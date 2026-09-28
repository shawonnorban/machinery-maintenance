@extends('layouts.app')
@section('title', __('settings.factories'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('settings.factories') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('settings.factories')" :subtitle="__('settings.factories_intro')">
        <x-slot:actions>
            <a href="{{ route('app.settings.factories.create') }}" class="btn btn-sm btn-info text-white">
                <i class="cil-plus" aria-hidden="true"></i> {{ __('settings.new_factory') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('settings.factory_name') }}</th>
                        <th>{{ __('settings.code') }}</th>
                        <th>{{ __('settings.timezone') }}</th>
                        <th class="text-end">{{ __('settings.machines') }}</th>
                        <th>{{ __('settings.status') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($factories as $factory)
                        <tr @class(['opacity-50' => $factory->status !== 'ACTIVE'])>
                            <td>
                                <span class="fw-semibold">{{ $factory->name }}</span>
                                <div class="small text-body-secondary">{{ $factory->address }}</div>
                            </td>
                            <td><code>{{ $factory->code }}</code></td>
                            <td class="small">{{ $factory->timezone }}</td>
                            <td class="text-end">{{ $factory->asset_count }}</td>
                            <td>
                                <x-status-pill :status="$factory->status"
                                               :tone="$factory->status === 'ACTIVE' ? 'success' : 'secondary'">
                                    {{ $factory->status === 'ACTIVE' ? __('settings.open') : __('settings.closed') }}
                                </x-status-pill>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('app.settings.factories.edit', $factory) }}"
                                       class="btn btn-sm btn-outline-secondary btn-icon"
                                       title="{{ __('common.edit') }}" aria-label="{{ __('common.edit') }}">
                                        <i class="cil-pencil" aria-hidden="true"></i>
                                    </a>

                                    <form method="POST" action="{{ route('app.settings.factories.toggle', $factory) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            {{ $factory->status === 'ACTIVE' ? __('settings.close') : __('settings.reopen') }}
                                        </button>
                                    </form>

                                    {{-- Refused by the action for any factory with machines
                                         in it, which is every factory that has ever run. --}}
                                    <form method="POST" action="{{ route('app.settings.factories.destroy', $factory) }}"
                                          onsubmit="return confirm(@js(__('settings.factory_delete_confirm', ['name' => $factory->name])))">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}">
                                            <i class="cil-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state :title="__('settings.no_factories')"
                                               :description="__('settings.no_factories_hint')">
                                    <x-slot:action>
                                        <a href="{{ route('app.settings.factories.create') }}"
                                           class="btn btn-sm btn-info text-white">
                                            {{ __('settings.new_factory') }}
                                        </a>
                                    </x-slot:action>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
