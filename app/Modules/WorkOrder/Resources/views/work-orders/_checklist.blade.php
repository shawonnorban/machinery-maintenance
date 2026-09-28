{{--
    Checklist execution (SRS 12, Frontend 5.5).

    Answers post one item at a time rather than as one big form. On a factory
    floor a connection drops mid-checklist, and losing fourteen answers because
    the fifteenth failed is the difference between a tool people use and a tool
    people work around.
--}}
<div class="card mb-3">
    <div class="card-header">
        <i class="cil-list-rich" aria-hidden="true"></i>
        <span>{{ __('work_order.checklist') }}</span>

        <span class="ms-auto d-flex align-items-center gap-2">
            <span class="text-body-secondary">
                {{ __('work_order.checklist_progress', ['answered' => $progress['answered'], 'total' => $progress['total']]) }}
            </span>

            @if ($progress['required_remaining'] > 0)
                <x-status-pill status="REMAINING" tone="warning">
                    {{ __('work_order.checklist_required_remaining', ['count' => $progress['required_remaining']]) }}
                </x-status-pill>
            @endif

            @if ($progress['failed'] > 0)
                <x-status-pill status="FAILED" tone="danger">
                    {{ trans_choice('work_order.checklist_failed_count', $progress['failed'], ['count' => $progress['failed']]) }}
                </x-status-pill>
            @endif
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="col-index">#</th>
                    <th>{{ __('work_order.checklist_item') }}</th>
                    <th style="min-width: 18rem">{{ __('work_order.answer') }}</th>
                    <th>{{ __('work_order.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    @php $result = $results[$item->id] ?? null; @endphp

                    <tr class="{{ $result?->isFailure() ? 'table-danger' : '' }}">
                        <td class="col-index">{{ $item->sequence }}</td>

                        <td>
                            <div class="fw-semibold">
                                {{ $item->label }}
                                @if ($item->required)
                                    <span class="text-danger" aria-hidden="true">*</span>
                                @endif
                            </div>

                            @if ($item->help_text)
                                <div class="text-body-secondary small">{{ $item->help_text }}</div>
                            @endif

                            @if ($item->is_safety_item)
                                <x-status-pill status="SAFETY" tone="danger">
                                    {{ __('work_order.safety_item') }}
                                </x-status-pill>
                            @endif

                            @if ($item->hasTolerance())
                                {{-- Shown, because a reading outside it is a failure
                                     whatever the technician taps. --}}
                                <div class="text-body-secondary small">
                                    {{ __('work_order.checklist_tolerance_range', [
                                        'min' => $item->tolerance_min ?? '−∞',
                                        'max' => $item->tolerance_max ?? '∞',
                                    ]) }}
                                    {{ $item->unit }}
                                </div>
                            @endif
                        </td>

                        <td>
                            @if ($canExecute)
                                <form method="POST"
                                      action="{{ route('app.work-orders.checklist.store', $workOrder) }}"
                                      enctype="multipart/form-data" class="d-flex flex-column gap-1">
                                    @csrf
                                    <input type="hidden" name="checklist_item_id" value="{{ $item->id }}">

                                    <div class="d-flex gap-1 align-items-center flex-wrap">
                                        @if ($item->isNumeric())
                                            <div class="input-group input-group-sm" style="max-width: 11rem">
                                                <input name="numeric_value" type="number" step="0.0001"
                                                       class="form-control"
                                                       value="{{ $result?->numeric_value }}"
                                                       placeholder="{{ __('work_order.reading') }}">
                                                @if ($item->unit)
                                                    <span class="input-group-text">{{ $item->unit }}</span>
                                                @endif
                                            </div>
                                        @elseif ($item->input_type === 'CHOICE')
                                            <select name="text_value" class="form-select form-select-sm"
                                                    style="max-width: 11rem">
                                                <option value="">—</option>
                                                @foreach ($item->options_json ?? [] as $option)
                                                    <option value="{{ $option }}" @selected($result?->text_value === $option)>
                                                        {{ $option }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif ($item->input_type === 'TEXT')
                                            <input name="text_value" type="text" class="form-control form-control-sm"
                                                   style="max-width: 14rem" maxlength="2000"
                                                   value="{{ $result?->text_value }}">
                                        @endif

                                        <select name="result" class="form-select form-select-sm" style="max-width: 7rem" required>
                                            @foreach (['PASS', 'FAIL'] as $option)
                                                <option value="{{ $option }}" @selected($result?->result === $option)>
                                                    {{ __('work_order.result_'.strtolower($option)) }}
                                                </option>
                                            @endforeach
                                            @unless ($item->required)
                                                {{-- Offered only where it is legitimate: a
                                                     required item marked N/A is how a
                                                     checklist gets emptied. --}}
                                                <option value="NA" @selected($result?->result === 'NA')>
                                                    {{ __('work_order.result_na') }}
                                                </option>
                                            @endunless
                                        </select>

                                        <button class="btn btn-sm btn-info text-white">{{ __('work_order.record') }}</button>
                                    </div>

                                    @if ($item->requires_note_on_fail || $item->requires_attachment_on_fail)
                                        <div class="d-flex gap-1 flex-wrap">
                                            @if ($item->requires_note_on_fail)
                                                <input name="observation" type="text" class="form-control form-control-sm"
                                                       style="max-width: 16rem" maxlength="2000"
                                                       value="{{ $result?->observation }}"
                                                       placeholder="{{ __('work_order.observation') }} ({{ __('work_order.result_fail') }})">
                                            @endif

                                            @if ($item->requires_attachment_on_fail)
                                                <input name="photo" type="file" class="form-control form-control-sm"
                                                       style="max-width: 14rem" accept="image/*,application/pdf">
                                            @endif
                                        </div>
                                    @endif
                                </form>
                            @else
                                @if ($result === null)
                                    <span class="text-body-secondary">—</span>
                                @else
                                    <div>
                                        @if ($result->numeric_value !== null)
                                            <span class="fw-semibold">{{ $result->numeric_value }} {{ $item->unit }}</span>
                                        @elseif ($result->text_value !== null)
                                            {{ $result->text_value }}
                                        @endif
                                    </div>
                                    @if ($result->observation)
                                        <div class="text-body-secondary small">{{ $result->observation }}</div>
                                    @endif
                                @endif
                            @endif
                        </td>

                        <td>
                            @if ($result === null)
                                <span class="text-body-secondary">—</span>
                            @else
                                @php
                                    $tone = match ($result->result) {
                                        'PASS' => 'success',
                                        'FAIL' => 'danger',
                                        default => 'secondary',
                                    };
                                @endphp

                                <x-status-pill :status="$result->result" :tone="$tone">
                                    {{ __('work_order.result_'.strtolower($result->result)) }}
                                </x-status-pill>

                                @if ($result->is_within_tolerance === false)
                                    <div class="small text-danger">{{ __('work_order.checklist_out_of_tolerance') }}</div>
                                @endif

                                @if ($result->followup_work_order_id !== null)
                                    <div class="small">
                                        <a href="{{ route('app.work-orders.show', $result->followup_work_order_id) }}">
                                            {{ __('work_order.followup_raised') }}
                                        </a>
                                    </div>
                                @endif

                                @if ($result->file_id !== null)
                                    <div class="small">
                                        <a href="{{ route('app.attachments.show', $result->file_id) }}" target="_blank" rel="noopener">
                                            {{ __('work_order.photo') }}
                                        </a>
                                    </div>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
