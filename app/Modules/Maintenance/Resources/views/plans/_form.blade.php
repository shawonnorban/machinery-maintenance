@php
    $plan = $plan ?? null;
    $timeRule = $plan?->rules->firstWhere('rule_type', 'TIME');
    $meterRule = $plan?->rules->firstWhere('rule_type', 'METER');
    $value = fn (string $field, $default = null) => old($field, $plan?->{$field} ?? $default);
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="name" class="form-label">{{ __('maintenance.name') }} <span class="text-danger">*</span></label>
                        <input id="name" name="name" type="text" maxlength="255" required
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ $value('name') }}">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- A plan covers one machine, or every machine of a type.
                         Both would make "which machines" unanswerable. --}}
                    <div class="col-md-6">
                        <label for="asset_id" class="form-label">{{ __('maintenance.target_asset') }}</label>
                        <select id="asset_id" name="asset_id" class="form-select @error('asset_id') is-invalid @enderror">
                            <option value="">&mdash;</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}" @selected($value('asset_id') === $asset->id)>
                                    {{ $asset->asset_code }} — {{ $asset->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('asset_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="asset_type_id" class="form-label">{{ __('maintenance.target_type') }}</label>
                        <select id="asset_type_id" name="asset_type_id" class="form-select">
                            <option value="">&mdash;</option>
                            @foreach ($assetTypes as $type)
                                <option value="{{ $type->id }}" @selected($value('asset_type_id') === $type->id)>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('maintenance.target_exactly_one') }}</div>
                    </div>

                    <div class="col-md-6">
                        <label for="maintenance_type_id" class="form-label">{{ __('maintenance.maintenance_type') }} <span class="text-danger">*</span></label>
                        <select id="maintenance_type_id" name="maintenance_type_id" required
                                class="form-select @error('maintenance_type_id') is-invalid @enderror">
                            @foreach ($maintenanceTypes as $type)
                                <option value="{{ $type->id }}" @selected($value('maintenance_type_id') === $type->id)>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('maintenance_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="template_version_id" class="form-label">{{ __('maintenance.template') }}</label>
                        <select id="template_version_id" name="template_version_id" class="form-select">
                            <option value="">&mdash;</option>
                            @foreach ($templates as $template)
                                @php($current = $template->currentVersion())
                                <option value="{{ $current->id }}" @selected($value('template_version_id') === $current->id)>
                                    {{ $template->name }} (v{{ $current->version_number }})
                                </option>
                            @endforeach
                        </select>
                        {{-- Only published versions are listed: a draft could
                             still change underneath the plan. --}}
                        <div class="form-text">{{ __('maintenance.template_must_be_published') }}</div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="trigger_type" class="form-label">{{ __('maintenance.trigger') }} <span class="text-danger">*</span></label>
                        <select id="trigger_type" name="trigger_type" required class="form-select" data-preview-input>
                            @foreach (['TIME', 'METER', 'COMBINED'] as $trigger)
                                <option value="{{ $trigger }}" @selected($value('trigger_type', 'TIME') === $trigger)>
                                    {{ __('maintenance.trigger_'.strtolower($trigger)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="schedule_mode" class="form-label">{{ __('maintenance.schedule_mode') }} <span class="text-danger">*</span></label>
                        <select id="schedule_mode" name="schedule_mode" required class="form-select" data-preview-input>
                            @foreach (['ROLLING', 'FIXED'] as $mode)
                                <option value="{{ $mode }}" @selected($value('schedule_mode', 'ROLLING') === $mode)>
                                    {{ __('maintenance.mode_'.strtolower($mode)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="rule_logic" class="form-label">{{ __('maintenance.rule_logic') }}</label>
                        <select id="rule_logic" name="rule_logic" class="form-select @error('rule_logic') is-invalid @enderror">
                            <option value="">&mdash;</option>
                            @foreach (['OR', 'AND'] as $logic)
                                <option value="{{ $logic }}" @selected($value('rule_logic') === $logic)>
                                    {{ __('maintenance.logic_'.strtolower($logic)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('rule_logic') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="interval_value" class="form-label">{{ __('maintenance.every') }}</label>
                        <input id="interval_value" name="interval_value" type="number" min="1" max="9999"
                               class="form-control @error('interval_value') is-invalid @enderror"
                               value="{{ old('interval_value', $timeRule ? (int) (float) $timeRule->value : 30) }}"
                               data-preview-input>
                        @error('interval_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="interval_unit" class="form-label">{{ __('maintenance.interval') }}</label>
                        <select id="interval_unit" name="interval_unit" class="form-select" data-preview-input>
                            @foreach (['DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR', 'HOUR'] as $unit)
                                <option value="{{ $unit }}" @selected(old('interval_unit', $timeRule?->unit ?? 'DAY') === $unit)>
                                    {{ $unit }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="meter_threshold" class="form-label">{{ __('maintenance.meter_threshold') }}</label>
                        <input id="meter_threshold" name="meter_threshold" type="number" step="0.0001" min="0"
                               class="form-control @error('meter_threshold') is-invalid @enderror"
                               value="{{ old('meter_threshold', $meterRule?->value) }}">
                        @error('meter_threshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="meter_type_id" class="form-label">{{ __('maintenance.meter_type') }}</label>
                        <select id="meter_type_id" name="meter_type_id" class="form-select">
                            <option value="">&mdash;</option>
                            @foreach ($meterTypes as $meterType)
                                <option value="{{ $meterType->id }}"
                                        @selected(old('meter_type_id', $meterRule?->meter_type_id) === $meterType->id)>
                                    {{ $meterType->name }} ({{ $meterType->unit }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">{{ __('maintenance.start_date') }} <span class="text-danger">*</span></label>
                        <input id="start_date" name="start_date" type="date" required
                               class="form-control @error('start_date') is-invalid @enderror"
                               value="{{ old('start_date', $plan?->start_date?->toDateString() ?? now()->toDateString()) }}"
                               data-preview-input>
                        @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="end_date" class="form-label">{{ __('maintenance.end_date') }}</label>
                        <input id="end_date" name="end_date" type="date" class="form-control @error('end_date') is-invalid @enderror"
                               value="{{ old('end_date', $plan?->end_date?->toDateString()) }}">
                        @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="priority" class="form-label">{{ __('asset.criticality') }}</label>
                        <select id="priority" name="priority" required class="form-select">
                            @foreach (['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'] as $priority)
                                <option value="{{ $priority }}" @selected($value('priority', 'MEDIUM') === $priority)>
                                    {{ __('asset.criticality_'.strtolower($priority)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="non_working_day_policy" class="form-label">{{ __('maintenance.non_working_day') }}</label>
                        <select id="non_working_day_policy" name="non_working_day_policy" required
                                class="form-select" data-preview-input>
                            @foreach (['NEXT_WORKING_DAY' => 'next', 'PREVIOUS_WORKING_DAY' => 'previous', 'NONE' => 'none'] as $policy => $key)
                                <option value="{{ $policy }}" @selected($value('non_working_day_policy', 'NEXT_WORKING_DAY') === $policy)>
                                    {{ __('maintenance.policy_'.$key) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="grace_period_minutes" class="form-label">{{ __('maintenance.grace') }}</label>
                        <input id="grace_period_minutes" name="grace_period_minutes" type="number" min="0" max="43200"
                               class="form-control" value="{{ $value('grace_period_minutes', 2880) }}">
                    </div>

                    <div class="col-md-4">
                        <label for="lead_time_days" class="form-label">{{ __('maintenance.lead_time') }}</label>
                        <input id="lead_time_days" name="lead_time_days" type="number" min="1" max="730"
                               class="form-control" value="{{ $value('lead_time_days', 30) }}">
                    </div>

                    <div class="col-md-4">
                        <label for="assigned_team_id" class="form-label">{{ __('maintenance.team') }}</label>
                        <select id="assigned_team_id" name="assigned_team_id" class="form-select">
                            <option value="">&mdash;</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}" @selected($value('assigned_team_id') === $team->id)>
                                    {{ $team->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="estimated_duration_minutes" class="form-label">{{ __('maintenance.duration') }}</label>
                        <input id="estimated_duration_minutes" name="estimated_duration_minutes" type="number" min="1" max="10080"
                               class="form-control" value="{{ $value('estimated_duration_minutes') }}">
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="requires_shutdown" id="requires_shutdown"
                                   value="1" @checked($value('requires_shutdown', false))>
                            <label class="form-check-label" for="requires_shutdown">
                                {{ __('maintenance.requires_shutdown') }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- The live preview (Frontend 5.4). A combined OR rule on a rolling
             schedule with a non-working-day policy is genuinely hard to reason
             about; showing the dates turns a guess into a check. --}}
        <div class="card position-sticky" style="top: 5rem">
            <div class="card-header">{{ __('maintenance.preview') }}</div>
            <div class="card-body">
                <ol class="list-unstyled mb-0" id="preview-dates">
                    <li class="text-body-secondary small">{{ __('maintenance.preview_needs_interval') }}</li>
                </ol>
                <p class="small text-body-secondary mt-3 mb-0" id="preview-note"></p>
                <p class="small text-body-secondary mt-2 mb-0">{{ __('maintenance.preview_hint') }}</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const url = @json(route('app.maintenance.plans.preview'));
    const movedLabel = @json(__('maintenance.preview_moved'));
    const list = document.getElementById('preview-dates');
    const note = document.getElementById('preview-note');
    const inputs = document.querySelectorAll('[data-preview-input]');

    async function refresh() {
        const payload = {
            schedule_mode: document.getElementById('schedule_mode')?.value,
            start_date: document.getElementById('start_date')?.value || null,
            interval_value: document.getElementById('interval_value')?.value || 0,
            interval_unit: document.getElementById('interval_unit')?.value,
            non_working_day_policy: document.getElementById('non_working_day_policy')?.value,
            factory_id: null,
        };

        try {
            const { data } = await window.http.post(url.replace('/api/v1', ''), payload, { baseURL: '' });
            render(data.data);
        } catch (e) {
            // A failed preview must never block the form: it is advisory.
            list.innerHTML = '';
        }
    }

    function render(result) {
        list.innerHTML = '';

        if (!result.dates.length) {
            const li = document.createElement('li');
            li.className = 'text-body-secondary small';
            li.textContent = result.note || '';
            list.appendChild(li);
            note.textContent = '';
            return;
        }

        result.dates.forEach((entry) => {
            const li = document.createElement('li');
            li.className = 'd-flex justify-content-between border-bottom py-1';
            const date = document.createElement('span');
            date.textContent = entry.date;
            li.appendChild(date);
            if (entry.moved) {
                const badge = document.createElement('span');
                badge.className = 'small text-body-secondary';
                badge.textContent = movedLabel;
                li.appendChild(badge);
            }
            list.appendChild(li);
        });

        note.textContent = result.note || '';
    }

    inputs.forEach((el) => el.addEventListener('change', refresh));
    refresh();
})();
</script>
@endpush
