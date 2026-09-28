{{--
    Derived downtime (SRS 17, ADR-048).

    Every figure states its basis. A report that silently changes basis is worse
    than one that says which it used: 10 minutes on the shift calendar and 500
    on the wall clock are both correct answers to different questions, and a
    manager needs to know which they are reading.
--}}
<div class="card mb-3">
    <div class="card-header">
        <i class="cil-speedometer" aria-hidden="true"></i>
        <span>{{ __('breakdown.downtime') }}</span>

        @if ($downtime !== null)
            <span class="ms-auto text-body-secondary small">
                v{{ $downtime->calculation_version }}
            </span>
        @endif
    </div>

    <div class="card-body">
        @if ($downtime === null)
            <p class="text-body-secondary mb-0">{{ __('common.not_available') }}</p>
        @else
            @if ($downtime->needs_review)
                {{-- Flagged, never silently dropped from the denominator
                     (ERD Section 12 rule 1). --}}
                <div class="alert alert-warning py-2">
                    <strong>{{ __('breakdown.needs_review') }}</strong>
                    <div class="small">{{ __('breakdown.needs_review_note') }}</div>
                </div>
            @endif

            <dl class="row mb-0">
                <dt class="col-sm-5">{{ __('breakdown.response_time') }}</dt>
                <dd class="col-sm-7">
                    @if ($downtime->response_minutes === null)
                        <span class="text-body-secondary">{{ __('common.not_available') }}</span>
                    @else
                        {{ number_format($downtime->response_minutes) }} {{ __('breakdown.minutes') }}
                    @endif
                    <div class="text-body-secondary small">{{ __('breakdown.response_time_note') }}</div>
                </dd>

                <dt class="col-sm-5">{{ __('breakdown.repair_time') }}</dt>
                <dd class="col-sm-7">
                    @if ($downtime->repair_minutes === null)
                        <span class="text-body-secondary">{{ __('common.not_available') }}</span>
                    @else
                        {{ number_format($downtime->repair_minutes) }} {{ __('breakdown.minutes') }}
                    @endif
                    <div class="text-body-secondary small">{{ __('breakdown.repair_time_note') }}</div>
                </dd>

                @if ($downtime->hold_minutes > 0)
                    <dt class="col-sm-5">{{ __('breakdown.hold_time') }}</dt>
                    <dd class="col-sm-7">
                        {{ number_format($downtime->hold_minutes) }} {{ __('breakdown.minutes') }}
                    </dd>
                @endif

                <dt class="col-sm-5">{{ __('breakdown.total_downtime') }}</dt>
                <dd class="col-sm-7">
                    <strong>{{ number_format($downtime->total_downtime_minutes ?? 0) }}</strong>
                    {{ __('breakdown.minutes') }}
                    <div class="text-body-secondary small">{{ __('breakdown.total_downtime_note') }}</div>
                </dd>

                <dt class="col-sm-5">{{ __('breakdown.calculation_basis') }}</dt>
                <dd class="col-sm-7">
                    {{ __('breakdown.basis_'.strtolower($downtime->calculation_basis ?? 'wall_clock')) }}
                    @if ($downtime->calendar_aware)
                        <div class="text-body-secondary small">{{ __('breakdown.calendar_aware') }}</div>
                    @endif
                </dd>

                <dt class="col-sm-5">{{ __('breakdown.downtime_class') }}</dt>
                <dd class="col-sm-7">
                    {{ __('breakdown.class_'.strtolower($downtime->downtime_class)) }}
                    @if ($downtime->reasonCode !== null)
                        <div class="text-body-secondary small">{{ $downtime->reasonCode->label() }}</div>
                    @endif
                    <div class="small {{ $downtime->counts_against_availability ? 'text-danger' : 'text-body-secondary' }}">
                        {{ $downtime->counts_against_availability
                            ? __('breakdown.counts_against_availability')
                            : __('breakdown.does_not_count') }}
                    </div>
                </dd>
            </dl>
        @endif
    </div>
</div>
