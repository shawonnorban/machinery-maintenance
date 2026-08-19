@extends('layouts.app')
@section('title', __('breakdown.report_breakdown'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.breakdowns.index') }}">{{ __('breakdown.breakdowns') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('breakdown.report_breakdown') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('breakdown.report_breakdown')" />

    <form method="POST" action="{{ route('app.breakdowns.store') }}">
        @csrf

        <div class="row">
            <div class="col-lg-7">
                {{-- Three fields is the whole required form. Somebody is standing
                     at a stopped machine; demanding a diagnosis here gets either a
                     delayed report or a guessed code, and both are worse than an
                     incomplete one. --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-warning" aria-hidden="true"></i>
                        <span>{{ __('breakdown.details') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="asset_id" class="form-label">{{ __('breakdown.asset') }}</label>
                            <select id="asset_id" name="asset_id" class="form-select" required data-tom-select>
                                <option value="">—</option>
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->id }}" @selected(old('asset_id') === $asset->id)>
                                        {{ $asset->asset_code }} — {{ $asset->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('asset_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="problem_description" class="form-label">
                                {{ __('breakdown.problem_description') }}
                            </label>
                            <textarea id="problem_description" name="problem_description" class="form-control"
                                      rows="4" required maxlength="5000">{{ old('problem_description') }}</textarea>
                            <div class="form-text">{{ __('breakdown.problem_description_hint') }}</div>
                            @error('problem_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="failure_at" class="form-label">{{ __('breakdown.failure_at') }}</label>
                                <input id="failure_at" name="failure_at" type="datetime-local"
                                       class="form-control" value="{{ old('failure_at') }}">
                                {{-- Blank means now. A machine that stopped an hour
                                     before anyone reported it is common, and that
                                     hour belongs to reporting, not to maintenance
                                     response. --}}
                                @error('failure_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="reported_at" class="form-label">{{ __('breakdown.reported_at') }}</label>
                                <input id="reported_at" name="reported_at" type="datetime-local"
                                       class="form-control" value="{{ old('reported_at') }}">
                                @error('reported_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header">
                        <i class="cil-options" aria-hidden="true"></i>
                        <span>{{ __('common.all') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="priority" class="form-label">{{ __('breakdown.priority') }}</label>
                                <select id="priority" name="priority" class="form-select">
                                    {{-- Blank defaults to the machine's criticality.
                                         A critical machine stopping is a critical
                                         breakdown, and the reporter should not have
                                         to decide that while a line is stopped. --}}
                                    <option value="">—</option>
                                    @foreach (['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'] as $option)
                                        <option value="{{ $option }}" @selected(old('priority') === $option)>
                                            {{ __('breakdown.priority_'.strtolower($option)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="severity" class="form-label">{{ __('breakdown.severity') }}</label>
                                <select id="severity" name="severity" class="form-select">
                                    @foreach (['CATASTROPHIC', 'MAJOR', 'MINOR', 'NEGLIGIBLE'] as $option)
                                        <option value="{{ $option }}" @selected(old('severity', 'MAJOR') === $option)>
                                            {{ __('breakdown.severity_'.strtolower($option)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="production_line_id" class="form-label">
                                    {{ __('breakdown.production_line') }}
                                </label>
                                <select id="production_line_id" name="production_line_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($productionLines as $line)
                                        <option value="{{ $line->id }}" @selected(old('production_line_id') === $line->id)>
                                            {{ $line->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label for="production_order_reference" class="form-label">
                                    {{ __('breakdown.production_order_reference') }}
                                </label>
                                <input id="production_order_reference" name="production_order_reference" type="text"
                                       class="form-control" value="{{ old('production_order_reference') }}"
                                       maxlength="255">
                            </div>

                            <div class="col-12">
                                <label for="failure_code_id" class="form-label">{{ __('breakdown.failure_code') }}</label>
                                <select id="failure_code_id" name="failure_code_id" class="form-select" data-tom-select>
                                    <option value="">—</option>
                                    @foreach ($failureCodes as $code)
                                        <option value="{{ $code->id }}" @selected(old('failure_code_id') === $code->id)>
                                            {{ $code->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                {{-- Optional at report time on purpose. Maintenance
                                     confirms or corrects it at closure, where the
                                     code and root cause are both required. --}}
                            </div>

                            <div class="col-12">
                                <label for="downtime_reason_code_id" class="form-label">
                                    {{ __('breakdown.reason_code') }}
                                </label>
                                <select id="downtime_reason_code_id" name="downtime_reason_code_id" class="form-select">
                                    <option value="">—</option>
                                    @foreach ($reasonCodes as $reason)
                                        <option value="{{ $reason->id }}" @selected(old('downtime_reason_code_id') === $reason->id)>
                                            {{ $reason->label() }}
                                            ({{ __('breakdown.class_'.strtolower($reason->downtime_class)) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger">{{ __('breakdown.report_breakdown') }}</button>
            <a href="{{ route('app.breakdowns.index') }}" class="btn btn-outline-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection
