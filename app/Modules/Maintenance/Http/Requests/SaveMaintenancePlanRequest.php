<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Http\Requests;

use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenancePlanRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            $this->route('plan') ? 'maintenance.plan.update' : 'maintenance.plan.create',
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Exactly one of these is required; which one is a domain rule
            // enforced in the action, so the API gets it too.
            'asset_id' => ['nullable', 'string', 'size:26'],
            'asset_type_id' => ['nullable', 'string', 'size:26'],
            'maintenance_type_id' => ['required', 'string', 'size:26'],
            'template_version_id' => ['nullable', 'string', 'size:26'],

            'trigger_type' => ['required', Rule::in(MaintenancePlan::TRIGGER_TYPES)],
            'schedule_mode' => ['required', Rule::in(MaintenancePlan::SCHEDULE_MODES)],
            'rule_logic' => ['nullable', Rule::in(['OR', 'AND'])],

            'interval_value' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'interval_unit' => ['nullable', Rule::in(MaintenancePlanRule::TIME_UNITS)],
            'meter_threshold' => ['nullable', 'numeric', 'min:0.0001'],
            'meter_type_id' => ['nullable', 'string', 'size:26'],
            'meter_unit' => ['nullable', 'string', 'max:32'],

            'priority' => ['required', Rule::in(['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'])],
            'grace_period_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'lead_time_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'non_working_day_policy' => ['required', Rule::in(['NONE', 'NEXT_WORKING_DAY', 'PREVIOUS_WORKING_DAY'])],
            'requires_shutdown' => ['sometimes', 'boolean'],
            'assigned_team_id' => ['nullable', 'string', 'size:26'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],

            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
        ];
    }
}
