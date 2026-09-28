@extends('layouts.app')
@section('title', __('technician.technicians'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('technician.technicians') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('technician.technicians')" :subtitle="__('technician.intro')">
        <x-slot:actions>
            <a href="{{ route('app.technicians.create') }}" class="btn btn-sm btn-info text-white">
                <i class="cil-plus" aria-hidden="true"></i> {{ __('technician.new_technician') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-sm-5">
                    <input type="search" class="form-control" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('technician.search') }}">
                </div>

                <div class="col-sm-4">
                    <select class="form-select" name="department_id">
                        <option value="">{{ __('technician.all_departments') }}</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') === $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-3 d-flex gap-2">
                    <button class="btn btn-outline-secondary">{{ __('common.search') }}</button>
                    <a href="{{ route('app.technicians.index') }}" class="btn btn-outline-secondary">
                        {{ __('common.clear') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('technician.name') }}</th>
                        <th>{{ __('technician.covers') }}</th>
                        <th>{{ __('technician.specialization') }}</th>
                        <th class="text-end">{{ __('technician.workload_limit') }}</th>
                        <th>{{ __('settings.status') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($technicians as $technician)
                        <tr @class(['opacity-50' => ! $technician->isActive()])>
                            <td>
                                <span class="fw-semibold">{{ $technician->name }}</span>
                                <div class="small text-body-secondary">{{ $technician->employee_id }}</div>
                            </td>
                            <td>
                                {{ $technician->factory?->name }}
                                @if ($technician->department)
                                    <div class="small text-body-secondary">
                                        {{ $technician->department->name }}
                                        @if ($technician->productionLine)
                                            &middot; {{ $technician->productionLine->name }}
                                        @endif
                                    </div>
                                @else
                                    {{-- No department named: they cover the whole
                                         factory, which is how a small factory works. --}}
                                    <div class="small text-body-secondary">{{ __('technician.whole_factory') }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $technician->specialization }}</td>
                            <td class="text-end">
                                {{ $technician->max_concurrent_work_orders ?: __('technician.no_limit') }}
                            </td>
                            <td>
                                <x-status-pill :status="$technician->status"
                                               :tone="$technician->isActive() ? 'success' : 'secondary'">
                                    {{ $technician->isActive() ? __('technician.active') : __('technician.inactive') }}
                                </x-status-pill>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('app.technicians.edit', $technician) }}"
                                       class="btn btn-sm btn-outline-secondary btn-icon"
                                       title="{{ __('common.edit') }}" aria-label="{{ __('common.edit') }}">
                                        <i class="cil-pencil" aria-hidden="true"></i>
                                    </a>

                                    <form method="POST" action="{{ route('app.technicians.toggle', $technician) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            {{ $technician->isActive() ? __('technician.deactivate') : __('technician.activate') }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('app.technicians.destroy', $technician) }}"
                                          onsubmit="return confirm(@js(__('technician.delete_confirm', ['name' => $technician->name])))">
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
                                <x-empty-state :title="__('technician.no_technicians')"
                                               :description="__('technician.no_technicians_hint')">
                                    <x-slot:action>
                                        <a href="{{ route('app.technicians.create') }}"
                                           class="btn btn-sm btn-info text-white">
                                            {{ __('technician.new_technician') }}
                                        </a>
                                    </x-slot:action>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($technicians->hasPages())
            <div class="card-footer">{{ $technicians->links() }}</div>
        @endif
    </div>
@endsection
