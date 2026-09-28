<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Services;

use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Metering\Models\AssetMeter;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Brings maintenance due when a meter crosses its threshold (ADR-012).
 *
 * Evaluated the moment a reading is posted, not on the nightly tick. A machine
 * that reaches 500 running hours at 02:00 should not keep running until
 * morning before anyone is told (ERD Section 7, scheduler rule 5).
 *
 * This is the half of "every 30 days OR every 500 running hours, whichever
 * occurs first" that the calendar generator cannot do.
 */
class MeterTriggerEvaluator
{
    /**
     * @return Collection<int, MaintenanceSchedule> occurrences this reading brought due
     */
    public function evaluate(AssetMeter $meter, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $triggered = collect();

        // An occurrence already generated with a meter target: has the reading
        // now passed it?
        $pending = MaintenanceSchedule::query()
            ->where('asset_id', $meter->asset_id)
            ->where('due_meter_type_id', $meter->meter_type_id)
            ->whereIn('status', ['PLANNED'])
            ->whereNotNull('due_meter')
            ->get();

        foreach ($pending as $schedule) {
            if ((float) $meter->current_value < (float) $schedule->due_meter) {
                continue;
            }

            $plan = MaintenancePlan::find($schedule->maintenance_plan_id);

            // With AND, reaching the meter threshold is not enough on its own:
            // the date must also have arrived (ADR-012).
            if ($plan !== null && $plan->trigger_type === 'COMBINED' && $plan->rule_logic === 'AND') {
                if ($schedule->due_at->isFuture()) {
                    continue;
                }
            }

            $schedule->forceFill([
                'status' => 'DUE',
                // Recorded so a combined plan's history can explain why an
                // occurrence appeared before its calendar date.
                'triggered_by' => 'METER',
                'due_at' => $schedule->due_at->isFuture() ? $now : $schedule->due_at,
            ])->save();

            $triggered->push($schedule);
        }

        $triggered = $triggered->merge($this->raiseForMeterOnlyPlans($meter, $now));

        return $triggered;
    }

    /**
     * A meter-only plan has no calendar date, so the calendar generator never
     * creates an occurrence for it. Its occurrence is born here, the first
     * time a reading crosses the threshold.
     *
     * @return Collection<int, MaintenanceSchedule>
     */
    private function raiseForMeterOnlyPlans(AssetMeter $meter, CarbonImmutable $now): Collection
    {
        $raised = collect();

        $plans = MaintenancePlan::query()
            ->where('active', true)
            ->where('trigger_type', 'METER')
            ->where(function ($q) use ($meter): void {
                $q->where('asset_id', $meter->asset_id)
                    ->orWhere('asset_type_id', $meter->asset->asset_type_id ?? null);
            })
            ->with('rules')
            ->get();

        foreach ($plans as $plan) {
            $rule = $plan->meterRule();

            if ($rule === null || $rule->meter_type_id !== $meter->meter_type_id) {
                continue;
            }

            $open = MaintenanceSchedule::where('maintenance_plan_id', $plan->id)
                ->where('asset_id', $meter->asset_id)
                ->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
                ->exists();

            if ($open) {
                continue;
            }

            $lastCompleted = MaintenanceSchedule::where('maintenance_plan_id', $plan->id)
                ->where('asset_id', $meter->asset_id)
                ->where('status', 'COMPLETED')
                ->orderByDesc('completed_at')
                ->first();

            // Threshold is measured from the reading at the last service, so
            // "every 500 hours" means 500 hours of running since the last one,
            // not 500 on the odometer.
            $baseline = $lastCompleted?->due_meter !== null
                ? (float) $lastCompleted->due_meter
                : 0.0;

            $threshold = $baseline + (float) $rule->value;

            if ((float) $meter->current_value < $threshold) {
                continue;
            }

            try {
                $schedule = MaintenanceSchedule::create([
                    'company_id' => $plan->company_id,
                    'maintenance_plan_id' => $plan->id,
                    'asset_id' => $meter->asset_id,
                    'due_at' => $now,
                    'due_meter' => number_format($threshold, 4, '.', ''),
                    'due_meter_type_id' => $meter->meter_type_id,
                    'status' => 'DUE',
                    'grace_until' => $now->addMinutes($plan->grace_period_minutes),
                    'generated_at' => $now,
                    'triggered_by' => 'METER',
                    'timezone' => $plan->timezone,
                ]);

                $raised->push($schedule);
            } catch (QueryException $e) {
                // Two readings posted in the same second must not raise the
                // same occurrence twice.
                if (($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
            }
        }

        return $raised;
    }
}
