<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenancePlanRule;
use App\Modules\Maintenance\Models\MaintenanceTemplateVersion;
use App\Modules\Maintenance\Services\ScheduleGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a maintenance plan and its trigger rules.
 *
 * The rules are the plan: everything else is metadata. Getting them wrong
 * produces a plan that silently generates nothing, or one that generates
 * work nobody asked for, so they are validated here rather than trusted
 * from the form.
 */
class SaveMaintenancePlan
{
    public function __construct(private readonly ScheduleGenerator $generator) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?MaintenancePlan $plan = null, ?string $userId = null): MaintenancePlan
    {
        $this->assertTargetIsUnambiguous($data);
        $this->assertRulesMatchTrigger($data);
        $this->assertTemplateIsPublished($data);

        return DB::transaction(function () use ($data, $plan, $userId): MaintenancePlan {
            $attributes = [
                'asset_id' => ($data['asset_id'] ?? null) ?: null,
                'asset_type_id' => ($data['asset_type_id'] ?? null) ?: null,
                'maintenance_type_id' => $data['maintenance_type_id'],
                'template_version_id' => ($data['template_version_id'] ?? null) ?: null,
                'name' => $data['name'],
                'trigger_type' => $data['trigger_type'],
                'schedule_mode' => $data['schedule_mode'] ?? 'ROLLING',
                'rule_logic' => $data['trigger_type'] === 'COMBINED' ? ($data['rule_logic'] ?? 'OR') : null,
                'priority' => $data['priority'] ?? 'MEDIUM',
                'grace_period_minutes' => (int) ($data['grace_period_minutes'] ?? 0),
                'lead_time_days' => (int) ($data['lead_time_days'] ?? 14),
                'non_working_day_policy' => $data['non_working_day_policy'] ?? 'NEXT_WORKING_DAY',
                'requires_shutdown' => (bool) ($data['requires_shutdown'] ?? false),
                'assigned_team_id' => ($data['assigned_team_id'] ?? null) ?: null,
                'estimated_duration_minutes' => ($data['estimated_duration_minutes'] ?? null) ?: null,
                'start_date' => $data['start_date'],
                'end_date' => ($data['end_date'] ?? null) ?: null,
            ];

            if ($plan === null) {
                // A new plan is created inactive. Activating it is a separate,
                // deliberate act, because activation immediately generates work.
                $plan = MaintenancePlan::create($attributes + [
                    'active' => false,
                    'created_by' => $userId,
                ]);
            } else {
                $plan->fill($attributes)->save();
            }

            $this->saveRules($plan, $data);

            return $plan->fresh(['rules']);
        });
    }

    /**
     * Activating generates the first occurrences immediately rather than
     * waiting for the nightly tick, so a planner sees the result of what they
     * just configured (API 8).
     */
    public function activate(MaintenancePlan $plan): MaintenancePlan
    {
        if ($plan->rules()->count() === 0) {
            throw ValidationException::withMessages([
                'active' => __('maintenance.plan_needs_rules'),
            ]);
        }

        if ($plan->hasEnded()) {
            throw ValidationException::withMessages([
                'active' => __('maintenance.plan_already_ended'),
            ])->status(409);
        }

        $plan->forceFill(['active' => true])->save();

        $this->generator->generateForPlan($plan->fresh(['rules']));

        return $plan->fresh();
    }

    public function deactivate(MaintenancePlan $plan): MaintenancePlan
    {
        $plan->forceFill(['active' => false])->save();

        // Occurrences already generated are left alone. Cancelling them would
        // erase work a technician may already be part-way through.
        return $plan->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveRules(MaintenancePlan $plan, array $data): void
    {
        $plan->rules()->delete();

        $trigger = $data['trigger_type'];

        if (in_array($trigger, ['TIME', 'COMBINED'], true)) {
            MaintenancePlanRule::create([
                'maintenance_plan_id' => $plan->id,
                'rule_type' => 'TIME',
                'operator' => 'EVERY',
                'value' => (string) $data['interval_value'],
                'unit' => $data['interval_unit'] ?? 'DAY',
            ]);
        }

        if (in_array($trigger, ['METER', 'COMBINED'], true)) {
            MaintenancePlanRule::create([
                'maintenance_plan_id' => $plan->id,
                'rule_type' => 'METER',
                'operator' => 'EVERY',
                'value' => (string) $data['meter_threshold'],
                'unit' => $data['meter_unit'] ?? 'HOUR',
                'meter_type_id' => $data['meter_type_id'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTargetIsUnambiguous(array $data): void
    {
        $hasAsset = filled($data['asset_id'] ?? null);
        $hasType = filled($data['asset_type_id'] ?? null);

        if ($hasAsset === $hasType) {
            // Both would make "which machines does this cover" unanswerable;
            // neither would cover nothing at all.
            throw ValidationException::withMessages([
                'asset_id' => __('maintenance.target_exactly_one'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertRulesMatchTrigger(array $data): void
    {
        $trigger = $data['trigger_type'] ?? null;

        if (in_array($trigger, ['TIME', 'COMBINED'], true)) {
            if ((int) ($data['interval_value'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'interval_value' => __('maintenance.interval_required'),
                ]);
            }
        }

        if (in_array($trigger, ['METER', 'COMBINED'], true)) {
            if ((float) ($data['meter_threshold'] ?? 0) <= 0 || blank($data['meter_type_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'meter_threshold' => __('maintenance.meter_threshold_required'),
                ]);
            }
        }

        if ($trigger === 'COMBINED' && ! in_array($data['rule_logic'] ?? null, ['OR', 'AND'], true)) {
            // No implicit default: "whichever occurs first" and "both required"
            // are different maintenance regimes (ADR-012).
            throw ValidationException::withMessages([
                'rule_logic' => __('maintenance.rule_logic_required'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTemplateIsPublished(array $data): void
    {
        $versionId = $data['template_version_id'] ?? null;

        if (blank($versionId)) {
            return;
        }

        $version = MaintenanceTemplateVersion::find($versionId);

        if ($version === null || ! $version->isPublished()) {
            // A draft could still change underneath the plan, so the work
            // orders it raises would not match the checklist that was reviewed.
            throw ValidationException::withMessages([
                'template_version_id' => __('maintenance.template_must_be_published'),
            ]);
        }
    }
}
