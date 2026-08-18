{{--
    Status history. Every transition is recorded with who and why, because
    "when did this actually get done, and who says so" is the question an audit
    asks first.
--}}
<div class="card mb-3">
    <div class="card-header">
        <i class="cil-history" aria-hidden="true"></i>
        <span>{{ __('work_order.timeline') }}</span>
    </div>

    <div class="list-group list-group-flush">
        @foreach ($workOrder->statusHistories as $history)
            <div class="list-group-item">
                <div class="d-flex align-items-center gap-2">
                    @if ($history->from_status !== null)
                        <span class="text-body-secondary small">
                            {{ __('work_order.status_'.strtolower($history->from_status)) }} →
                        </span>
                    @endif

                    @include('work_order::work-orders._status', ['status' => $history->to_status])

                    <span class="ms-auto text-body-secondary small">
                        {{ $history->changed_at->format('Y-m-d H:i') }}
                    </span>
                </div>

                @if ($history->reason)
                    <div class="small text-body-secondary mt-1">{{ $history->reason }}</div>
                @endif
            </div>
        @endforeach
    </div>
</div>

@if ($workOrder->holds->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header">
            <i class="cil-media-pause" aria-hidden="true"></i>
            <span>{{ __('work_order.hold_reason') }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('work_order.hold_reason') }}</th>
                        <th>{{ __('work_order.started_at') }}</th>
                        <th>{{ __('work_order.ended_at') }}</th>
                        <th class="text-end">{{ __('work_order.minutes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($workOrder->holds as $hold)
                        <tr>
                            <td>
                                {{ __('work_order.hold_reason_'.strtolower($hold->reason_code)) }}
                                @if ($hold->notes)
                                    <div class="text-body-secondary small">{{ $hold->notes }}</div>
                                @endif
                            </td>
                            <td>{{ $hold->started_at->format('Y-m-d H:i') }}</td>
                            <td>{{ $hold->ended_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="text-end">{{ $hold->minutes !== null ? number_format($hold->minutes) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Stated on the screen, because a manager reading a long repair time
             should be able to see that most of it was waiting for a part. --}}
        <div class="card-footer small text-body-secondary">{{ __('work_order.repair_time_note') }}</div>
    </div>
@endif
