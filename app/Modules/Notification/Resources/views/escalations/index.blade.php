@extends('layouts.app')
@section('title', __('notification.escalations'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('notification.escalations') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('notification.escalations')" :subtitle="__('notification.escalations_intro')" />

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('notification.rule_event') }}</th>
                        <th>{{ __('notification.severity') }}</th>
                        <th class="text-end">{{ __('notification.after') }}</th>
                        <th class="text-end">{{ __('notification.level') }}</th>
                        <th>{{ __('notification.tell') }}</th>
                        <th>{{ __('notification.factory') }}</th>
                        <th class="text-end">{{ __('common.actions') }}</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($rules as $rule)
                        <tr @class(['opacity-50' => ! $rule->active])>
                            {{-- The event code itself, not its notification title: those carry
                                 placeholders like :asset and read as nonsense out of context.
                                 A configuration screen should name exactly what the rule
                                 matches on. --}}
                            <td class="small">{{ $rule->event_type }}</td>
                            <td class="small">{{ $rule->severity ?? __('notification.any_severity') }}</td>
                            <td class="text-end">
                                {{ trans_choice('notification.minutes', $rule->delay_minutes, [
                                    'count' => $rule->delay_minutes,
                                ]) }}
                            </td>
                            <td class="text-end">{{ $rule->escalation_level }}</td>
                            <td>{{ $rule->role?->name }}</td>
                            <td class="small text-body-secondary">
                                {{ $rule->factory?->name ?? __('notification.every_factory') }}
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <form method="POST" action="{{ route('app.settings.escalations.toggle', $rule) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">
                                            {{ $rule->active ? __('notification.pause') : __('notification.resume') }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('app.settings.escalations.destroy', $rule) }}"
                                          onsubmit="return confirm(@js(__('notification.remove_rule_confirm')))">
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
                            <td colspan="7" class="p-0">
                                <x-empty-state :title="__('notification.no_rules')"
                                               :description="__('notification.no_rules_hint')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('notification.new_rule') }}</div>

        <div class="card-body">
            <form method="POST" action="{{ route('app.settings.escalations.store') }}" class="row g-2 align-items-end">
                @csrf

                <div class="col-md-4">
                    <label for="event_type" class="form-label mb-1">{{ __('notification.rule_event') }}</label>
                    <select id="event_type" name="event_type" class="form-select form-select-sm" required>
                        @foreach ($eventTypes as $type)
                            <option value="{{ $type }}" @selected(old('event_type') === $type)>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="severity" class="form-label mb-1">{{ __('notification.severity') }}</label>
                    <select id="severity" name="severity" class="form-select form-select-sm">
                        <option value="">{{ __('notification.any_severity') }}</option>
                        @foreach ($severities as $severity)
                            <option value="{{ $severity }}">{{ $severity }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="delay_minutes" class="form-label mb-1">{{ __('notification.after') }}</label>
                    <input id="delay_minutes" name="delay_minutes" type="number" min="1" max="10080"
                           class="form-control form-control-sm" value="{{ old('delay_minutes', 30) }}" required>
                    @error('delay_minutes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-2">
                    <label for="escalation_level" class="form-label mb-1">{{ __('notification.level') }}</label>
                    <input id="escalation_level" name="escalation_level" type="number" min="1" max="5"
                           class="form-control form-control-sm" value="{{ old('escalation_level', 1) }}" required>
                    @error('escalation_level')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-2">
                    <label for="escalation_role_id" class="form-label mb-1">{{ __('notification.tell') }}</label>
                    <select id="escalation_role_id" name="escalation_role_id" class="form-select form-select-sm" required>
                        <option value="">—</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('escalation_role_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="factory_id" class="form-label mb-1">{{ __('notification.factory') }}</label>
                    <select id="factory_id" name="factory_id" class="form-select form-select-sm">
                        <option value="">{{ __('notification.every_factory') }}</option>
                        @foreach ($factories as $factory)
                            <option value="{{ $factory->id }}">{{ $factory->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 d-flex align-items-end pb-1">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="stop_on_acknowledge"
                               name="stop_on_acknowledge" value="1" checked>
                        <label class="form-check-label small" for="stop_on_acknowledge">
                            {{ __('notification.stop_on_acknowledge') }}
                        </label>
                    </div>
                </div>

                <div class="col-md-4">
                    <button class="btn btn-sm btn-info text-white w-100">{{ __('notification.add_rule') }}</button>
                </div>

                <div class="col-12">
                    {{-- A role, not a person: a rule that names Karim stops working
                         the week Karim is on leave, which is exactly the week
                         somebody needs it. --}}
                    <div class="form-text">{{ __('notification.role_not_person_hint') }}</div>
                </div>
            </form>
        </div>
    </div>
@endsection
