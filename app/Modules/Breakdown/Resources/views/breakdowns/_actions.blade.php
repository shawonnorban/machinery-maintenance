{{-- Only transitions the state machine allows are rendered. A button that
     returns 409 teaches people to distrust the interface (Frontend 3.4). --}}
<div class="d-flex flex-wrap gap-2">
    @if ($breakdown->canTransitionTo('ACKNOWLEDGED') && $breakdown->status === 'REPORTED')
        @can('breakdown.breakdown.acknowledge')
            <form method="POST" action="{{ route('app.breakdowns.acknowledge', $breakdown) }}">
                @csrf
                <button class="btn btn-sm btn-warning">{{ __('breakdown.acknowledge') }}</button>
            </form>
        @endcan
    @endif

    @if (in_array($breakdown->status, ['ACKNOWLEDGED', 'ASSIGNED'], true))
        @can('breakdown.breakdown.assign')
            <button type="button" class="btn btn-sm btn-outline-primary"
                    data-coreui-toggle="modal" data-coreui-target="#assign-modal">
                {{ __('breakdown.assign') }}
            </button>
        @endcan
    @endif

    @if ($breakdown->isOpen() && $breakdown->technician_arrival_at === null && $breakdown->status !== 'REPORTED')
        @can('breakdown.breakdown.repair')
            <form method="POST" action="{{ route('app.breakdowns.arrive', $breakdown) }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">{{ __('breakdown.record_arrival') }}</button>
            </form>
        @endcan
    @endif

    @if ($breakdown->canTransitionTo('IN_REPAIR') && $breakdown->status !== 'ON_HOLD')
        @can('breakdown.breakdown.repair')
            <form method="POST" action="{{ route('app.breakdowns.start-repair', $breakdown) }}">
                @csrf
                <button class="btn btn-sm btn-primary">
                    <i class="cil-media-play" aria-hidden="true"></i> {{ __('breakdown.start_repair') }}
                </button>
            </form>
        @endcan
    @endif

    @if ($breakdown->status === 'IN_REPAIR')
        @can('breakdown.breakdown.repair')
            <button type="button" class="btn btn-sm btn-outline-warning"
                    data-coreui-toggle="modal" data-coreui-target="#bd-hold-modal">
                {{ __('breakdown.hold') }}
            </button>

            <form method="POST" action="{{ route('app.breakdowns.complete-repair', $breakdown) }}">
                @csrf
                <button class="btn btn-sm btn-success">
                    <i class="cil-check" aria-hidden="true"></i> {{ __('breakdown.complete_repair') }}
                </button>
            </form>
        @endcan
    @endif

    @if ($breakdown->status === 'ON_HOLD')
        @can('breakdown.breakdown.repair')
            <form method="POST" action="{{ route('app.breakdowns.resume', $breakdown) }}">
                @csrf
                <button class="btn btn-sm btn-primary">{{ __('breakdown.resume') }}</button>
            </form>
        @endcan
    @endif

    @if ($breakdown->canTransitionTo('PRODUCTION_RESUMED'))
        @can('breakdown.breakdown.repair')
            {{-- Separate from "repair done" on purpose. The gap between the
                 machine being fixed and the line running again is real lost
                 output, and it belongs to nobody unless it is measured. --}}
            <form method="POST" action="{{ route('app.breakdowns.resume-production', $breakdown) }}">
                @csrf
                <button class="btn btn-sm btn-success">{{ __('breakdown.resume_production') }}</button>
            </form>
        @endcan
    @endif

    @if ($breakdown->canTransitionTo('CLOSED'))
        @can('breakdown.breakdown.close')
            <button type="button" class="btn btn-sm btn-dark"
                    data-coreui-toggle="modal" data-coreui-target="#close-modal">
                {{ __('breakdown.close') }}
            </button>
        @endcan
    @endif

    @can('work_order.work_order.create')
        @unless ($breakdown->isTerminal())
            <form method="POST" action="{{ route('app.breakdowns.work-order', $breakdown) }}">
                @csrf
                <button class="btn btn-sm btn-outline-info">{{ __('breakdown.raise_work_order') }}</button>
            </form>
        @endunless
    @endcan

    @if ($breakdown->canTransitionTo('CANCELLED'))
        @can('breakdown.breakdown.close')
            <button type="button" class="btn btn-sm btn-outline-danger ms-auto"
                    data-coreui-toggle="modal" data-coreui-target="#bd-cancel-modal">
                {{ __('breakdown.cancel') }}
            </button>
        @endcan
    @endif
</div>

<div class="modal fade" id="assign-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('app.breakdowns.assign', $breakdown) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('breakdown.assign') }}</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"
                        aria-label="{{ __('common.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <label for="assigned_technician_id" class="form-label">{{ __('breakdown.technician') }}</label>
                <select id="assigned_technician_id" name="assigned_technician_id" class="form-select" required>
                    <option value="">—</option>
                    @foreach ($technicians as $technician)
                        <option value="{{ $technician->id }}"
                            @selected($breakdown->assigned_technician_id === $technician->id)>
                            {{ $technician->name }} ({{ $technician->employee_id }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-coreui-dismiss="modal">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit" class="btn btn-info text-white">{{ __('breakdown.assign') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="bd-hold-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('app.breakdowns.hold', $breakdown) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('breakdown.hold') }}</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"
                        aria-label="{{ __('common.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="bd_reason_code" class="form-label">{{ __('breakdown.hold_reason') }}</label>
                    <select id="bd_reason_code" name="reason_code" class="form-select" required>
                        @foreach (App\Modules\Breakdown\Models\Breakdown::HOLD_REASONS as $reason)
                            <option value="{{ $reason }}">
                                {{ __('breakdown.hold_reason_'.strtolower($reason)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <label for="bd_hold_notes" class="form-label">{{ __('breakdown.notes') }}</label>
                <textarea id="bd_hold_notes" name="notes" class="form-control" rows="2" maxlength="2000"></textarea>
                <div class="form-text">{{ __('breakdown.repair_time_note') }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-coreui-dismiss="modal">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit" class="btn btn-warning">{{ __('breakdown.hold') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Closing demands a failure code and a root cause. A breakdown closed with
     no recorded cause is a machine that broke for no reason, and the failure
     reports are built from exactly these two fields (ERD Section 10 rule 3). --}}
<div class="modal fade" id="close-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" action="{{ route('app.breakdowns.close', $breakdown) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('breakdown.close') }}</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"
                        aria-label="{{ __('common.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small">{{ __('breakdown.closure_reason_note') }}</div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="close_failure_code_id" class="form-label">{{ __('breakdown.failure_code') }}</label>
                        <select id="close_failure_code_id" name="failure_code_id" class="form-select" required>
                            <option value="">—</option>
                            @foreach ($failureCodes as $code)
                                <option value="{{ $code->id }}" @selected($breakdown->failure_code_id === $code->id)>
                                    {{ $code->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="close_root_cause_id" class="form-label">{{ __('breakdown.root_cause') }}</label>
                        <select id="close_root_cause_id" name="root_cause_id" class="form-select" required>
                            <option value="">—</option>
                            @foreach ($rootCauses as $cause)
                                <option value="{{ $cause->id }}" @selected($breakdown->root_cause_id === $cause->id)>
                                    {{ $cause->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="corrective_action" class="form-label">{{ __('breakdown.corrective_action') }}</label>
                        <textarea id="corrective_action" name="corrective_action" class="form-control"
                                  rows="2" maxlength="5000">{{ $breakdown->corrective_action }}</textarea>
                    </div>

                    <div class="col-12">
                        <label for="preventive_action" class="form-label">{{ __('breakdown.preventive_action') }}</label>
                        <textarea id="preventive_action" name="preventive_action" class="form-control"
                                  rows="2" maxlength="5000">{{ $breakdown->preventive_action }}</textarea>
                    </div>

                    <div class="col-12">
                        <label for="closure_notes" class="form-label">{{ __('breakdown.closure_notes') }}</label>
                        <textarea id="closure_notes" name="closure_notes" class="form-control"
                                  rows="2" maxlength="5000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-coreui-dismiss="modal">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit" class="btn btn-dark">{{ __('breakdown.close') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="bd-cancel-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('app.breakdowns.cancel', $breakdown) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('breakdown.cancel') }}</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"
                        aria-label="{{ __('common.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <label for="bd_cancellation_reason" class="form-label">
                    {{ __('breakdown.cancellation_reason') }}
                </label>
                <input id="bd_cancellation_reason" name="cancellation_reason" type="text"
                       class="form-control" required maxlength="255">
                <div class="form-text">{{ __('breakdown.cancel_needs_reason') }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-coreui-dismiss="modal">
                    {{ __('common.back') }}
                </button>
                <button type="submit" class="btn btn-danger">{{ __('breakdown.cancel') }}</button>
            </div>
        </form>
    </div>
</div>
