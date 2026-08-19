@extends('layouts.app')
@section('title', __('audit.audit_log'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('audit.audit_log') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('audit.audit_log')" :subtitle="__('audit.append_only')" />

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-sm-6 col-lg-2">
                    <label for="action" class="form-label">{{ __('audit.action') }}</label>
                    <select class="form-select" id="action" name="action">
                        <option value="">{{ __('audit.all_actions') }}</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>
                                {{ __('audit.actions.'.$action) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label for="entity_type" class="form-label">{{ __('audit.entity_type') }}</label>
                    <select class="form-select" id="entity_type" name="entity_type">
                        <option value="">{{ __('audit.all_types') }}</option>
                        @foreach ($entityTypes as $type)
                            <option value="{{ $type }}" @selected(request('entity_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label for="user_id" class="form-label">{{ __('audit.who') }}</label>
                    <select class="form-select" id="user_id" name="user_id" data-tom-select>
                        <option value="">{{ __('audit.all_users') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(request('user_id') === $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label for="from" class="form-label">{{ __('audit.from') }}</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ request('from') }}">
                </div>

                <div class="col-sm-6 col-lg-2">
                    <label for="to" class="form-label">{{ __('audit.to') }}</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ request('to') }}">
                </div>

                <div class="col-sm-6 col-lg-2 d-flex align-items-end gap-2">
                    <button class="btn btn-info text-white">{{ __('audit.filter') }}</button>
                    <a href="{{ route('app.audit-logs') }}" class="btn btn-outline-secondary">{{ __('audit.clear') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('audit.when') }}</th>
                        <th>{{ __('audit.who') }}</th>
                        <th>{{ __('audit.action') }}</th>
                        <th>{{ __('audit.entity') }}</th>
                        <th>{{ __('audit.changes') }}</th>
                        <th>{{ __('audit.context') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>
                                <a href="{{ route('app.audit-logs.show', $log) }}">@dt($log->created_at)</a>
                            </td>
                            <td>
                                {{-- A row written by the scheduler has no user.
                                     Saying "System" is more honest than leaving
                                     the cell blank. --}}
                                {{ $log->user?->name ?? __('audit.system') }}

                                @if ($log->impersonated_by)
                                    <div class="small text-danger">{{ __('audit.impersonated_by') }}</div>
                                @endif
                            </td>
                            <td>
                                <x-status-pill :status="$log->action" :tone="match ($log->action) {
                                    'DELETED', 'LOGIN_FAILED', 'SECURITY_EVENT' => 'danger',
                                    'CREATED' => 'success',
                                    'COST_CHANGED', 'PERMISSION_CHANGED' => 'warning',
                                    default => 'secondary',
                                }">
                                    {{ __('audit.actions.'.$log->action) }}
                                </x-status-pill>
                            </td>
                            <td>
                                {{ $log->entity_label ?? '—' }}
                                <div class="small text-body-secondary">{{ $log->entity_type }}</div>
                            </td>
                            <td class="small text-body-secondary">
                                {{ implode(', ', array_slice($log->changed_fields_json ?? [], 0, 5)) }}
                                @if (count($log->changed_fields_json ?? []) > 5)
                                    …
                                @endif
                            </td>
                            <td class="small">{{ __('audit.contexts.'.$log->context) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-empty-state :title="__('audit.no_entries')"
                                               :description="__('audit.no_entries_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $logs->links() }}
@endsection
