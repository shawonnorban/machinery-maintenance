@extends('layouts.app')
@section('title', __('calendar.calendar'))

@php
    $weekdays = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday'];
@endphp

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('calendar.calendar') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('calendar.calendar')" :subtitle="__('calendar.intro')" />

    @if ($factories->count() > 1)
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-sm-6">
                        <label for="factory_id" class="form-label mb-1">{{ __('calendar.factory') }}</label>
                        <select id="factory_id" name="factory_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($factories as $option)
                                <option value="{{ $option->id }}" @selected($factory?->id === $option->id)>
                                    {{ $option->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($factory === null)
        <x-empty-state :title="__('calendar.no_factory')" :description="__('calendar.no_factory_hint')" />
    @else
        <div class="row">
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header">{{ __('calendar.working_week') }}</div>

                    <div class="card-body">
                        @if ($calendar)
                            <p class="mb-2">
                                <span class="fw-semibold">{{ __('calendar.mode_'.strtolower($calendar->operating_mode)) }}</span>
                                <span class="text-body-secondary small d-block">
                                    {{ __('calendar.in_force_since', ['date' => $calendar->effective_from->toDateString()]) }}
                                </span>
                            </p>

                            <p class="mb-3">
                                @if ($calendar->isContinuous())
                                    {{-- A plant that never stops has no weekly off
                                         day, and availability is measured against the
                                         whole clock. --}}
                                    {{ __('calendar.no_weekly_off') }}
                                @else
                                    {{ __('calendar.weekly_off') }}:
                                    @forelse ($calendar->weekly_off_days ?? [] as $day)
                                        <span class="badge bg-secondary">{{ __('calendar.'.$weekdays[$day]) }}</span>
                                    @empty
                                        <span class="text-body-secondary">{{ __('calendar.none') }}</span>
                                    @endforelse
                                @endif
                            </p>
                        @else
                            <div class="alert alert-warning">{{ __('calendar.not_set_yet') }}</div>
                        @endif

                        <hr>

                        {{-- Superseded from a date rather than edited: last
                             quarter's availability was computed against last
                             quarter's week, and rewriting the week would restate a
                             number somebody already reported. --}}
                        <form method="POST" action="{{ route('app.settings.calendar.store') }}">
                            @csrf
                            <input type="hidden" name="factory_id" value="{{ $factory->id }}">

                            <div class="mb-3">
                                <label for="operating_mode" class="form-label">{{ __('calendar.operating_mode') }}</label>
                                <select id="operating_mode" name="operating_mode" class="form-select">
                                    <option value="SHIFT_BASED" @selected(old('operating_mode', $calendar?->operating_mode) === 'SHIFT_BASED')>
                                        {{ __('calendar.mode_shift_based') }}
                                    </option>
                                    <option value="CONTINUOUS" @selected(old('operating_mode', $calendar?->operating_mode) === 'CONTINUOUS')>
                                        {{ __('calendar.mode_continuous') }}
                                    </option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <span class="form-label d-block">{{ __('calendar.weekly_off') }}</span>
                                @foreach ($weekdays as $number => $key)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="off-{{ $number }}"
                                               name="weekly_off_days[]" value="{{ $number }}"
                                               @checked(in_array($number, old('weekly_off_days', $calendar?->weekly_off_days ?? []), false))>
                                        <label class="form-check-label small" for="off-{{ $number }}">
                                            {{ __('calendar.'.$key) }}
                                        </label>
                                    </div>
                                @endforeach
                                @error('weekly_off_days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="cal_effective_from" class="form-label">{{ __('calendar.in_force_from') }}</label>
                                <input id="cal_effective_from" name="effective_from" type="date" class="form-control"
                                       value="{{ old('effective_from', $today) }}" required>
                                @error('effective_from')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <button class="btn btn-info text-white">{{ __('calendar.put_in_force') }}</button>
                        </form>
                    </div>

                    @if ($history->count() > 1)
                        <div class="table-responsive border-top">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('calendar.in_force_from') }}</th>
                                        <th>{{ __('calendar.until') }}</th>
                                        <th>{{ __('calendar.weekly_off') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($history as $row)
                                        <tr>
                                            <td>{{ $row->effective_from->toDateString() }}</td>
                                            <td class="text-body-secondary">
                                                {{ $row->effective_to?->toDateString() ?: __('calendar.in_force') }}
                                            </td>
                                            <td class="small">
                                                @forelse ($row->weekly_off_days ?? [] as $day)
                                                    {{ __('calendar.'.$weekdays[$day]) }}@if (! $loop->last),@endif
                                                @empty
                                                    —
                                                @endforelse
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header">{{ __('calendar.shifts') }}</div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('calendar.shift') }}</th>
                                    <th>{{ __('calendar.hours') }}</th>
                                    <th>{{ __('calendar.days') }}</th>
                                    <th class="text-end">{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($shifts as $shift)
                                    <tr @class(['opacity-50' => $shift->status !== 'ACTIVE'])>
                                        <td>
                                            {{ $shift->name }}
                                            <div class="small text-body-secondary">{{ $shift->code }}</div>
                                        </td>
                                        <td>
                                            {{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }}
                                            @if ($shift->crosses_midnight)
                                                {{-- A night shift ends before it starts on
                                                     the clock; saying so stops a reader
                                                     assuming a typo. --}}
                                                <span class="badge bg-dark">{{ __('calendar.overnight') }}</span>
                                            @endif
                                            @if ($shift->is_overtime)
                                                <span class="badge bg-secondary">{{ __('calendar.overtime') }}</span>
                                            @endif
                                        </td>
                                        <td class="small">
                                            @foreach ($shift->days_of_week ?? [] as $day)
                                                {{ __('calendar.'.$weekdays[$day].'_short') }}@if (! $loop->last) @endif
                                            @endforeach
                                        </td>
                                        <td class="text-end">
                                            @if ($shift->status === 'ACTIVE')
                                                <form method="POST"
                                                      action="{{ route('app.settings.calendar.shifts.destroy', $shift) }}"
                                                      onsubmit="return confirm(@js(__('calendar.end_shift_confirm', ['name' => $shift->name])))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        {{ __('calendar.end_shift') }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="small text-body-secondary">
                                                    {{ __('calendar.ended_on', ['date' => $shift->effective_to?->toDateString()]) }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-body-secondary">{{ __('calendar.no_shifts') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('app.settings.calendar.shifts.store') }}"
                              class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="factory_id" value="{{ $factory->id }}">

                            <div class="col-md-4">
                                <label for="shift_name" class="form-label mb-1">{{ __('calendar.shift_name') }}</label>
                                <input id="shift_name" name="name" type="text" class="form-control form-control-sm"
                                       value="{{ old('name') }}" required maxlength="255">
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-2">
                                <label for="shift_code" class="form-label mb-1">{{ __('calendar.code') }}</label>
                                <input id="shift_code" name="code" type="text"
                                       class="form-control form-control-sm text-uppercase"
                                       value="{{ old('code') }}" required maxlength="32">
                                @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-3">
                                <label for="start_time" class="form-label mb-1">{{ __('calendar.starts') }}</label>
                                <input id="start_time" name="start_time" type="time" class="form-control form-control-sm"
                                       value="{{ old('start_time', '08:00') }}" required>
                            </div>

                            <div class="col-md-3">
                                <label for="end_time" class="form-label mb-1">{{ __('calendar.ends') }}</label>
                                <input id="end_time" name="end_time" type="time" class="form-control form-control-sm"
                                       value="{{ old('end_time', '20:00') }}" required>
                            </div>

                            <div class="col-12">
                                <span class="form-label d-block mb-1">{{ __('calendar.days') }}</span>
                                @foreach ($weekdays as $number => $key)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="shiftday-{{ $number }}"
                                               name="days_of_week[]" value="{{ $number }}"
                                               @checked(in_array($number, old('days_of_week', [1, 2, 3, 4, 6, 7]), false))>
                                        <label class="form-check-label small" for="shiftday-{{ $number }}">
                                            {{ __('calendar.'.$key.'_short') }}
                                        </label>
                                    </div>
                                @endforeach
                                @error('days_of_week')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-4">
                                <label for="shift_effective_from" class="form-label mb-1">
                                    {{ __('calendar.in_force_from') }}
                                </label>
                                <input id="shift_effective_from" name="effective_from" type="date"
                                       class="form-control form-control-sm" value="{{ old('effective_from', $today) }}"
                                       required>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="is_overtime"
                                           name="is_overtime" value="1" @checked(old('is_overtime'))>
                                    <label class="form-check-label small" for="is_overtime">
                                        {{ __('calendar.is_overtime') }}
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <button class="btn btn-sm btn-info text-white w-100">
                                    {{ __('calendar.add_shift') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">{{ __('calendar.holidays') }}</div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('calendar.date') }}</th>
                                    <th>{{ __('calendar.occasion') }}</th>
                                    <th class="text-end">{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($holidays as $holiday)
                                    <tr>
                                        <td>{{ $holiday->date->toDateString() }}</td>
                                        <td>
                                            {{ $holiday->name }}
                                            @if ($holiday->is_working_day)
                                                {{-- The same table says the opposite thing
                                                     too: a working Friday during a
                                                     shipment week. --}}
                                                <span class="badge bg-success">{{ __('calendar.working_day') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <form method="POST"
                                                  action="{{ route('app.settings.calendar.holidays.destroy', $holiday) }}">
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
                                        <td colspan="3" class="text-body-secondary">{{ __('calendar.no_holidays') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('app.settings.calendar.holidays.store') }}"
                              class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="factory_id" value="{{ $factory->id }}">

                            <div class="col-md-4">
                                <label for="holiday_date" class="form-label mb-1">{{ __('calendar.date') }}</label>
                                <input id="holiday_date" name="date" type="date" class="form-control form-control-sm"
                                       value="{{ old('date') }}" required>
                                @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-5">
                                <label for="holiday_name" class="form-label mb-1">{{ __('calendar.occasion') }}</label>
                                <input id="holiday_name" name="name" type="text" class="form-control form-control-sm"
                                       value="{{ old('name') }}" required maxlength="255">
                            </div>

                            <div class="col-md-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="is_working_day"
                                           name="is_working_day" value="1" @checked(old('is_working_day'))>
                                    <label class="form-check-label small" for="is_working_day">
                                        {{ __('calendar.is_working_day') }}
                                    </label>
                                </div>
                                <button class="btn btn-sm btn-info text-white w-100">{{ __('calendar.add_holiday') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
