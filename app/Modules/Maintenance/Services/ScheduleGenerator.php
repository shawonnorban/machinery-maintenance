<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Calendar\Services\WorkingTimeService;
use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenancePlanRule;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Metering\Models\AssetMeter;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Generates concrete maintenance occurrences from a plan (SRS 10, ERD 7).
 *
 * Two schedule modes, and the difference matters to a factory:
 *
 *   ROLLING  next due is measured from when the last one was COMPLETED. A
 *            service done a week late pushes the next one a week out, which
 *            is what "every 500 running hours" actually means.
 *
 *   FIXED    next due follows the calendar anchor regardless of when the last
 *            was done. The first of the month stays the first of the month,
 *            which is what an inspection regime requires.
 *
 * Generation is idempotent. The unique index on (plan, asset, due_at) enforces
 * that in the database rather than trusting the job never to run twice.
 */
class ScheduleGenerator
{
    public function __construct(
        private readonly SettingsResolver $settings,
        private readonly WorkingTimeService $workingTime,
    ) {}

    /**
     * Generates forward for one plan across every asset it covers.
     *
     * @return array{created: int, skipped: int}
     */
    public function generateForPlan(MaintenancePlan $plan, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        if (! $plan->active || $plan->hasEnded()) {
            return ['created' => 0, 'skipped' => 0];
        }

        $plan->loadMissing('rules');

        $horizon = $this->horizon($plan, $now);
        $created = 0;
        $skipped = 0;

        foreach ($plan->targetAssets() as $asset) {
            $result = $this->generateForAsset($plan, $asset, $now, $horizon);
            $created += $result['created'];
            $skipped += $result['skipped'];
        }

        $plan->forceFill([
            'last_generated_at' => $now,
            'next_due_at' => $this->earliestOpenDueAt($plan),
        ])->save();

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @return array{created: int, skipped: int}
     */
    private function generateForAsset(
        MaintenancePlan $plan,
        Asset $asset,
        CarbonImmutable $now,
        CarbonImmutable $horizon,
    ): array {
        $created = 0;
        $skipped = 0;

        // An occurrence already waiting is not replaced. Generating a second
        // one would show a technician two identical jobs for the same machine.
        $open = MaintenanceSchedule::where('maintenance_plan_id', $plan->id)
            ->where('asset_id', $asset->id)
            ->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
            ->exists();

        if ($open) {
            return ['created' => 0, 'skipped' => 1];
        }

        $cursor = $this->firstDueAt($plan, $asset, $now);

        // Bounded loop. A misconfigured interval of zero would otherwise spin
        // forever generating occurrences at the same instant.
        $guard = 0;

        while ($cursor !== null && $cursor->lessThanOrEqualTo($horizon) && $guard++ < 200) {
            if ($plan->end_date !== null && $cursor->greaterThan(CarbonImmutable::parse($plan->end_date)->endOfDay())) {
                break;
            }

            $adjusted = $this->applyWorkingDayPolicy($plan, $asset, $cursor);

            if ($this->create($plan, $asset, $adjusted, $now)) {
                $created++;
            } else {
                $skipped++;
            }

            $next = $this->advance($plan, $adjusted);

            if ($next === null || $next->lessThanOrEqualTo($cursor)) {
                break;
            }

            $cursor = $next;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * The next due instant for an asset that has no open occurrence.
     */
    private function firstDueAt(MaintenancePlan $plan, Asset $asset, CarbonImmutable $now): ?CarbonImmutable
    {
        $timeRule = $plan->timeRule();

        if ($timeRule === null) {
            // A meter-only plan has no calendar date to generate from; its
            // occurrence is raised when a reading crosses the threshold.
            return null;
        }

        $anchor = CarbonImmutable::parse($plan->start_date)->startOfDay();

        $last = MaintenanceSchedule::where('maintenance_plan_id', $plan->id)
            ->where('asset_id', $asset->id)
            ->where('status', 'COMPLETED')
            ->orderByDesc('completed_at')
            ->first();

        if ($plan->isRolling()) {
            // Measured from completion. A service done a week late pushes the
            // next one a week out.
            $from = $last?->completed_at !== null
                ? CarbonImmutable::parse($last->completed_at)
                : $anchor;

            $due = $last === null ? $anchor : $this->addInterval($from, $timeRule);

            // A plan activated long after its start date should not backfill
            // every occurrence it missed.
            return $due->lessThan($now) && $last === null ? $now->startOfDay() : $due;
        }

        // FIXED: the grid is anchor + n × interval and never shifts. Walk it
        // forward to the first slot still ahead of the floor.
        //
        // The floor is the last completion, or one interval before now when
        // nothing has been done. Using "now" itself would drop the current
        // period's occurrence, so a plan activated on its own start date
        // would silently skip that day; using the raw anchor would backfill
        // every occurrence since the plan was nominally supposed to start.
        $due = $anchor;

        $floor = $last?->completed_at !== null
            ? CarbonImmutable::parse($last->completed_at)
            : $this->subtractInterval($now, $timeRule);

        $guard = 0;

        while ($due->lessThanOrEqualTo($floor) && $guard++ < 5000) {
            $advanced = $this->addInterval($due, $timeRule);

            if ($advanced->lessThanOrEqualTo($due)) {
                break;
            }

            $due = $advanced;
        }

        return $due;
    }

    private function advance(MaintenancePlan $plan, CarbonImmutable $from): ?CarbonImmutable
    {
        $timeRule = $plan->timeRule();

        if ($timeRule === null) {
            return null;
        }

        // Rolling schedules generate one occurrence at a time: the next due
        // date is not knowable until this one is completed.
        if ($plan->isRolling()) {
            return null;
        }

        return $this->addInterval($from, $timeRule);
    }

    private function subtractInterval(CarbonImmutable $from, MaintenancePlanRule $rule): CarbonImmutable
    {
        $value = (int) round((float) $rule->value);

        if ($value <= 0) {
            return $from;
        }

        return match ($rule->unit) {
            'HOUR' => $from->subHours($value),
            'DAY' => $from->subDays($value),
            'WEEK' => $from->subWeeks($value),
            'MONTH' => $from->subMonthsNoOverflow($value),
            'QUARTER' => $from->subMonthsNoOverflow($value * 3),
            'YEAR' => $from->subYearsNoOverflow($value),
            default => $from->subDays($value),
        };
    }

    private function addInterval(CarbonImmutable $from, MaintenancePlanRule $rule): CarbonImmutable
    {
        $value = (int) round((float) $rule->value);

        if ($value <= 0) {
            return $from;
        }

        return match ($rule->unit) {
            'HOUR' => $from->addHours($value),
            'DAY' => $from->addDays($value),
            'WEEK' => $from->addWeeks($value),
            'MONTH' => $from->addMonthsNoOverflow($value),
            'QUARTER' => $from->addMonthsNoOverflow($value * 3),
            'YEAR' => $from->addYearsNoOverflow($value),
            default => $from->addDays($value),
        };
    }

    /**
     * Moves a due date off a non-working day (SRS 47.3).
     *
     * Without this, a monthly PM landing on a Friday is reported overdue on
     * Saturday morning through nobody's fault.
     */
    private function applyWorkingDayPolicy(
        MaintenancePlan $plan,
        Asset $asset,
        CarbonImmutable $due,
    ): CarbonImmutable {
        $policy = $plan->non_working_day_policy ?? 'NONE';

        if ($policy === 'NONE') {
            return $due;
        }

        $factory = Factory::find($asset->current_factory_id);

        if ($factory === null) {
            return $due;
        }

        return $this->workingTime->applyNonWorkingDayPolicy($factory, $due, $policy);
    }

    private function create(
        MaintenancePlan $plan,
        Asset $asset,
        CarbonImmutable $dueAt,
        CarbonImmutable $now,
    ): bool {
        try {
            MaintenanceSchedule::create([
                'company_id' => $plan->company_id,
                'maintenance_plan_id' => $plan->id,
                'asset_id' => $asset->id,
                'due_at' => $dueAt,
                'due_meter' => $this->dueMeterFor($plan, $asset),
                'due_meter_type_id' => $plan->meterRule()?->meter_type_id,
                'status' => $dueAt->lessThanOrEqualTo($now) ? 'DUE' : 'PLANNED',
                'grace_until' => $dueAt->addMinutes($plan->grace_period_minutes),
                'generated_at' => $now,
                'triggered_by' => 'TIME',
                'timezone' => $plan->timezone,
            ]);

            return true;
        } catch (QueryException $e) {
            // The unique index did its job: this occurrence already exists.
            // Re-running the generator must not duplicate work (ERD 7 rule 2).
            if ($this->isDuplicate($e)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * For a combined plan, the meter value at which this occurrence also
     * becomes due. Populating both is what makes "whichever occurs first"
     * work (ADR-012).
     */
    private function dueMeterFor(MaintenancePlan $plan, Asset $asset): ?string
    {
        $meterRule = $plan->meterRule();

        if ($meterRule === null) {
            return null;
        }

        $meter = AssetMeter::where('asset_id', $asset->id)
            ->where('meter_type_id', $meterRule->meter_type_id)
            ->first();

        if ($meter === null) {
            return null;
        }

        return number_format(
            (float) $meter->current_value + (float) $meterRule->value,
            4,
            '.',
            '',
        );
    }

    private function horizon(MaintenancePlan $plan, CarbonImmutable $now): CarbonImmutable
    {
        $companyHorizon = $this->settings->int('maintenance.schedule_generation_horizon_days');

        // The plan's own lead time never exceeds the company horizon: a plan
        // asking for two years of occurrences would fill the table with rows
        // nobody will look at for 23 months.
        $days = min($plan->lead_time_days, $companyHorizon);

        return $now->addDays(max($days, 1))->endOfDay();
    }

    private function earliestOpenDueAt(MaintenancePlan $plan): ?CarbonImmutable
    {
        $next = MaintenanceSchedule::where('maintenance_plan_id', $plan->id)
            ->whereIn('status', MaintenanceSchedule::OPEN_STATUSES)
            ->orderBy('due_at')
            ->value('due_at');

        return $next === null ? null : CarbonImmutable::parse($next);
    }

    private function isDuplicate(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    /**
     * Runs every active plan for the current tenant. Called by the scheduled
     * job and after a plan is activated.
     *
     * @return array{plans: int, created: int, skipped: int}
     */
    public function generateAll(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $created = 0;
        $skipped = 0;
        $plans = 0;

        MaintenancePlan::where('active', true)
            ->with('rules')
            ->chunkById(100, function (Collection $chunk) use (&$created, &$skipped, &$plans, $now): void {
                foreach ($chunk as $plan) {
                    $result = $this->generateForPlan($plan, $now);
                    $created += $result['created'];
                    $skipped += $result['skipped'];
                    $plans++;
                }
            });

        return ['plans' => $plans, 'created' => $created, 'skipped' => $skipped];
    }
}
