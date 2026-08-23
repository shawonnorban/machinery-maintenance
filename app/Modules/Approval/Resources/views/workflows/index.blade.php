@extends('layouts.app')
@section('title', __('approval.workflows'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('approval.workflows') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('approval.workflows')" :subtitle="__('approval.workflows_intro')" />

    @forelse ($workflows as $workflow)
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <span class="fw-semibold">{{ $workflow->name }}</span>
                    <span class="badge bg-light text-dark ms-2">
                        {{ __('approval.entity_'.strtolower($workflow->entity_type)) }}
                    </span>
                    @unless ($workflow->active)
                        <span class="badge bg-secondary ms-1">{{ __('approval.inactive') }}</span>
                    @endunless
                </span>

                <span class="d-flex align-items-center gap-2">
                    @if ($requestCounts[$workflow->id] > 0)
                        {{-- Said rather than enforced: a factory may change its
                             chain, and the requests already raised keep the
                             context they froze. --}}
                        <span class="small text-body-secondary">
                            {{ trans_choice('approval.already_used', $requestCounts[$workflow->id], [
                                'count' => $requestCounts[$workflow->id],
                            ]) }}
                        </span>
                    @endif

                    <form method="POST" action="{{ route('app.settings.approval-workflows.toggle', $workflow) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary">
                            {{ $workflow->active ? __('approval.deactivate') : __('approval.activate') }}
                        </button>
                    </form>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 3rem">{{ __('approval.step') }}</th>
                            <th>{{ __('approval.rule_name') }}</th>
                            <th>{{ __('approval.applies_when') }}</th>
                            <th>{{ __('approval.signed_by') }}</th>
                            <th class="text-end">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workflow->rules as $rule)
                            <tr>
                                <td class="text-body-secondary">{{ $rule->sequence }}</td>
                                <td>{{ $rule->name }}</td>
                                <td class="small">
                                    @foreach ($rule->condition_json ?? [] as $field => $value)
                                        <span class="badge bg-light text-dark">
                                            {{ __('approval.condition_'.$field) }}:
                                            {{ is_array($value) ? implode(', ', $value) : $value }}
                                        </span>
                                    @endforeach
                                </td>
                                <td>{{ $rule->role?->name }}</td>
                                <td class="text-end">
                                    <form method="POST"
                                          action="{{ route('app.settings.approval-workflows.rules.destroy', [$workflow, $rule]) }}"
                                          onsubmit="return confirm(@js(__('approval.remove_rule_confirm')))">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                title="{{ __('common.delete') }}"
                                                aria-label="{{ __('common.delete') }}">
                                            <i class="cil-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-body-secondary">
                                    {{-- A workflow with no rules signs nothing, which
                                         is the same as not having it. --}}
                                    {{ __('approval.no_rules') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-body border-top">
                <form method="POST" action="{{ route('app.settings.approval-workflows.rules.store', $workflow) }}"
                      class="row g-2 align-items-end">
                    @csrf

                    <div class="col-md-3">
                        <label class="form-label mb-1" for="rule_name_{{ $workflow->id }}">
                            {{ __('approval.rule_name') }}
                        </label>
                        <input id="rule_name_{{ $workflow->id }}" name="name" type="text"
                               class="form-control form-control-sm" required maxlength="255">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1" for="min_cost_{{ $workflow->id }}">
                            {{ __('approval.condition_min_cost') }}
                        </label>
                        <input id="min_cost_{{ $workflow->id }}" name="min_cost" type="number" step="0.01" min="0"
                               class="form-control form-control-sm">
                        @error('min_cost')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label mb-1" for="max_cost_{{ $workflow->id }}">
                            {{ __('approval.condition_max_cost') }}
                        </label>
                        <input id="max_cost_{{ $workflow->id }}" name="max_cost" type="number" step="0.01" min="0"
                               class="form-control form-control-sm">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label mb-1" for="role_{{ $workflow->id }}">
                            {{ __('approval.signed_by') }}
                        </label>
                        <select id="role_{{ $workflow->id }}" name="role_id" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                        @error('role_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-2">
                        <button class="btn btn-sm btn-info text-white w-100">{{ __('approval.add_step') }}</button>
                    </div>

                    <div class="col-12">
                        {{-- A role, not a person: a chain that names Karim stops
                             working the week Karim is on leave. --}}
                        <div class="form-text">{{ __('approval.role_not_person_hint') }}</div>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="card mb-4">
            <x-empty-state :title="__('approval.no_workflows')" :description="__('approval.no_workflows_hint')" />
        </div>
    @endforelse

    <div class="card">
        <div class="card-header">{{ __('approval.new_workflow') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('app.settings.approval-workflows.store') }}"
                  class="row g-2 align-items-end">
                @csrf

                <div class="col-md-5">
                    <label for="workflow_name" class="form-label mb-1">{{ __('approval.workflow_name') }}</label>
                    <input id="workflow_name" name="name" type="text" class="form-control form-control-sm"
                           required maxlength="255">
                </div>

                <div class="col-md-4">
                    <label for="entity_type" class="form-label mb-1">{{ __('approval.applies_to') }}</label>
                    <select id="entity_type" name="entity_type" class="form-select form-select-sm" required>
                        @foreach ($entityTypes as $type)
                            <option value="{{ $type }}">{{ __('approval.entity_'.strtolower($type)) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <button class="btn btn-sm btn-info text-white w-100">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
