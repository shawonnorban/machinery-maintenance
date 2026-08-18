{{--
    The action bar. Only transitions the state machine actually allows are
    rendered: offering a button that returns 409 teaches people to distrust the
    interface (Frontend 3.4).
--}}
<div class="d-flex flex-wrap gap-2">
    @if ($workOrder->canTransitionTo('SCHEDULED') && $workOrder->status === 'DRAFT')
        @can('work_order.work_order.update')
            <form method="POST" action="{{ route('app.work-orders.schedule', $workOrder) }}">
                @csrf
                <button class="btn btn-sm btn-info text-white">{{ __('work_order.schedule') }}</button>
            </form>
        @endcan
    @endif

    @if ($workOrder->canTransitionTo('IN_PROGRESS') && $workOrder->status === 'ASSIGNED')
        @can('work_order.work_order.start')
            <form method="POST" action="{{ route('app.work-orders.start', $workOrder) }}">
                @csrf
                <button class="btn btn-sm btn-success">
                    <i class="cil-media-play" aria-hidden="true"></i> {{ __('work_order.start') }}
                </button>
            </form>
        @endcan
    @endif

    @if ($workOrder->status === 'IN_PROGRESS')
        @can('work_order.work_order.start')
            <button type="button" class="btn btn-sm btn-outline-warning"
                    data-coreui-toggle="modal" data-coreui-target="#hold-modal">
                <i class="cil-media-pause" aria-hidden="true"></i> {{ __('work_order.hold') }}
            </button>
        @endcan

        @can('work_order.work_order.complete')
            <form method="POST" action="{{ route('app.work-orders.complete', $workOrder) }}">
                @csrf
                <button class="btn btn-sm btn-success"
                        @disabled($progress['required_remaining'] > 0)
                        @if ($progress['required_remaining'] > 0)
                            title="{{ __('work_order.checklist_required_remaining', ['count' => $progress['required_remaining']]) }}"
                        @endif>
                    <i class="cil-check" aria-hidden="true"></i> {{ __('work_order.complete') }}
                </button>
            </form>
        @endcan
    @endif

    @if ($workOrder->status === 'ON_HOLD')
        @can('work_order.work_order.start')
            <form method="POST" action="{{ route('app.work-orders.resume', $workOrder) }}">
                @csrf
                <button class="btn btn-sm btn-success">
                    <i class="cil-media-play" aria-hidden="true"></i> {{ __('work_order.resume') }}
                </button>
            </form>
        @endcan
    @endif

    @if ($workOrder->status === 'COMPLETED' && $workOrder->requires_verification)
        @can('work_order.work_order.verify')
            <form method="POST" action="{{ route('app.work-orders.verify', $workOrder) }}">
                @csrf
                {{-- Refused server-side too. Disabling it here only saves the
                     round trip and explains why. --}}
                <button class="btn btn-sm btn-info text-white"
                        @disabled($workOrder->completed_by === auth()->id())
                        @if ($workOrder->completed_by === auth()->id())
                            title="{{ __('work_order.verified_by_other') }}"
                        @endif>
                    {{ __('work_order.verify') }}
                </button>
            </form>
        @endcan
    @endif

    @if ($workOrder->canTransitionTo('CLOSED'))
        @can('work_order.work_order.close')
            <form method="POST" action="{{ route('app.work-orders.close', $workOrder) }}">
                @csrf
                <button class="btn btn-sm btn-dark">{{ __('work_order.close') }}</button>
            </form>
        @endcan
    @endif

    @if ($workOrder->status === 'COMPLETED')
        @can('work_order.work_order.reopen')
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    data-coreui-toggle="modal" data-coreui-target="#reopen-modal">
                {{ __('work_order.reopen') }}
            </button>
        @endcan
    @endif

    @if ($workOrder->canTransitionTo('CANCELLED'))
        @can('work_order.work_order.cancel')
            <button type="button" class="btn btn-sm btn-outline-danger ms-auto"
                    data-coreui-toggle="modal" data-coreui-target="#cancel-modal">
                {{ __('work_order.cancel') }}
            </button>
        @endcan
    @endif
</div>

{{-- Hold: a reason code, not free text, so a spare-part shortage shows up as
     its own cause instead of inflating repair time (ADR-051). --}}
<div class="modal fade" id="hold-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('app.work-orders.hold', $workOrder) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('work_order.hold') }}</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"
                        aria-label="{{ __('common.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="reason_code" class="form-label">{{ __('work_order.hold_reason') }}</label>
                    <select id="reason_code" name="reason_code" class="form-select" required>
                        @foreach (App\Modules\WorkOrder\Models\WorkOrder::HOLD_REASONS as $reason)
                            <option value="{{ $reason }}">
                                {{ __('work_order.hold_reason_'.strtolower($reason)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="hold_notes" class="form-label">{{ __('work_order.notes') }}</label>
                    <textarea id="hold_notes" name="notes" class="form-control" rows="2" maxlength="2000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-coreui-dismiss="modal">
                    {{ __('common.cancel') }}
                </button>
                <button type="submit" class="btn btn-warning">{{ __('work_order.hold') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="cancel-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('app.work-orders.cancel', $workOrder) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('work_order.cancel') }}</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"
                        aria-label="{{ __('common.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <label for="cancellation_reason" class="form-label">{{ __('work_order.cancellation_reason') }}</label>
                <input id="cancellation_reason" name="cancellation_reason" type="text"
                       class="form-control" required maxlength="255">
                {{-- Cancelled maintenance is a compliance exception, so it is
                     never anonymous. --}}
                <div class="form-text">{{ __('work_order.cancel_needs_reason') }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-coreui-dismiss="modal">
                    {{ __('common.back') }}
                </button>
                <button type="submit" class="btn btn-danger">{{ __('work_order.cancel') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="reopen-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('app.work-orders.reopen', $workOrder) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">{{ __('work_order.reopen') }}</h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"
                        aria-label="{{ __('common.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <label for="reopen_reason" class="form-label">{{ __('work_order.reopen_reason') }}</label>
                <input id="reopen_reason" name="reason" type="text" class="form-control" required maxlength="255">
                {{-- Counted as well as recorded: a high reopen rate is itself a
                     maintenance-quality signal. --}}
                <div class="form-text">{{ __('work_order.reopen_needs_reason') }}</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-coreui-dismiss="modal">
                    {{ __('common.back') }}
                </button>
                <button type="submit" class="btn btn-secondary">{{ __('work_order.reopen') }}</button>
            </div>
        </form>
    </div>
</div>
