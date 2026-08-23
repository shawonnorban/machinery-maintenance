@extends('layouts.app')
@section('title', __('user.users'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('user.users') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('user.users')" :subtitle="__('user.users_intro')">
        <x-slot:actions>
            <a href="{{ route('app.settings.roles') }}" class="btn btn-sm btn-outline-secondary">
                {{ __('user.roles') }}
            </a>
            <a href="{{ route('app.settings.users.create') }}" class="btn btn-sm btn-info text-white">
                <i class="cil-plus" aria-hidden="true"></i> {{ __('user.new_user') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('user_password'))
        {{-- The one and only time this is readable. Flashed, never stored
             anywhere it can be asked for again. --}}
        <div class="alert alert-warning">
            <div class="fw-semibold">{{ __('user.password_shown_once', ['email' => session('user_password_for')]) }}</div>
            <code class="d-block my-2 user-select-all">{{ session('user_password') }}</code>
            <div class="small">{{ __('user.password_hint') }}</div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-sm-6">
                    <input type="search" class="form-control" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('user.search') }}">
                </div>
                <div class="col-sm-3 d-flex gap-2">
                    <button class="btn btn-outline-secondary">{{ __('common.search') }}</button>
                    <a href="{{ route('app.settings.users') }}" class="btn btn-outline-secondary">
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
                        <th>{{ __('user.name') }}</th>
                        <th>{{ __('user.roles') }}</th>
                        <th>{{ __('user.factory') }}</th>
                        <th>{{ __('settings.status') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($users as $person)
                        @php($membership = $person->memberships->first())
                        <tr @class(['opacity-50' => $membership?->status !== 'ACTIVE'])>
                            <td>
                                <span class="fw-semibold">{{ $person->name }}</span>
                                <div class="small text-body-secondary">{{ $person->email }}</div>
                            </td>
                            <td>
                                @foreach ($person->roleAssignments as $assignment)
                                    <span class="badge bg-secondary">{{ $assignment->role?->name }}</span>
                                @endforeach
                            </td>
                            <td class="small">
                                {{ $person->roleAssignments->pluck('factory.name')->filter()->unique()->join(', ')
                                    ?: __('user.company_wide') }}
                            </td>
                            <td>
                                <x-status-pill :status="$membership?->status ?? 'UNKNOWN'"
                                               :tone="$membership?->status === 'ACTIVE' ? 'success' : 'secondary'">
                                    {{ $membership?->status === 'ACTIVE' ? __('user.active') : __('user.suspended') }}
                                </x-status-pill>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('app.settings.users.edit', $person) }}"
                                       class="btn btn-sm btn-outline-secondary btn-icon"
                                       title="{{ __('common.edit') }}" aria-label="{{ __('common.edit') }}">
                                        <i class="cil-pencil" aria-hidden="true"></i>
                                    </a>

                                    <form method="POST" action="{{ route('app.settings.users.password', $person) }}"
                                          onsubmit="return confirm(@js(__('user.reset_confirm', ['name' => $person->name])))">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">{{ __('user.reset_password') }}</button>
                                    </form>

                                    <form method="POST" action="{{ route('app.settings.users.toggle', $person) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            {{ $membership?->status === 'ACTIVE' ? __('user.suspend') : __('user.restore') }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('app.settings.users.destroy', $person) }}"
                                          onsubmit="return confirm(@js(__('user.remove_confirm', ['name' => $person->name])))">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('user.remove') }}" aria-label="{{ __('user.remove') }}">
                                            <i class="cil-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state :title="__('user.no_users')" :description="__('user.no_users_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="card-footer">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
