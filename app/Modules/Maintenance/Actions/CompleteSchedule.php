<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Actions;

use App\Modules\Maintenance\Models\MaintenancePlan;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Services\ScheduleGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks an occurrence done and generates the next one.
 *
 * For a rolling plan this is the only point at which the next due date becomes
 * knowable, which is why generation is triggered here rather than waiting for
 * the nightly tick (SRS 10).
 */
class CompleteSchedule
{
    public function __construct(private readonly ScheduleGenerator $generator) {}

    public function handle(
        MaintenanceSchedule $schedule,
        ?CarbonImmutable $completedAt = null,
        ?string $userId = null,
    ): MaintenanceSchedule {
        if (! $schedule->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('maintenance.schedule_not_open', ['status' => $schedule->status]),
            ])->status(409);
        }

        $completedAt ??= CarbonImmutable::now();

        return DB::transaction(function () use ($schedule, $completedAt, $userId): MaintenanceSchedule {
            $schedule->forceFill([
                'status' => 'COMPLETED',
                'completed_at' => $completedAt,
            ])->save();

            $plan = MaintenancePlan::find($schedule->maintenance_plan_id);

            if ($plan !== null) {
                $plan->forceFill(['last_completed_at' => $completedAt])->save();

                // Rolling plans measure the next interval from completion, so
                // the successor can only be created now.
                $this->generator->generateForPlan($plan->fresh(), $completedAt);
            }

            unset($userId);

            return $schedule->fresh();
        });
    }

    public function skip(MaintenanceSchedule $schedule, string $reason, ?string $userId = null): MaintenanceSchedule
    {
        if (! $schedule->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('maintenance.schedule_not_open', ['status' => $schedule->status]),
            ])->status(409);
        }

        if (blank($reason)) {
            // A skip is a compliance exception, so it is never anonymous
            // (SRS 31.1, PM compliance).
            throw ValidationException::withMessages([
                'skipped_reason' => __('maintenance.skip_needs_reason'),
            ]);
        }

        return DB::transaction(function () use ($schedule, $reason, $userId): MaintenanceSchedule {
            $schedule->forceFill([
                'status' => 'SKIPPED',
                'skipped_reason' => $reason,
                'skipped_by' => $userId,
            ])->save();

            $plan = MaintenancePlan::find($schedule->maintenance_plan_id);

            if ($plan !== null) {
                // A skipped occurrence still advances the cycle: the machine
                // is not serviced twice as often because one was missed.
                $this->generator->generateForPlan($plan->fresh());
            }

            return $schedule->fresh();
        });
    }

    public function reschedule(
        MaintenanceSchedule $schedule,
        CarbonImmutable $newDueAt,
        string $reason,
        ?string $userId = null,
    ): MaintenanceSchedule {
        if (! $schedule->isOpen()) {
            throw ValidationException::withMessages([
                'status' => __('maintenance.schedule_not_open', ['status' => $schedule->status]),
            ])->status(409);
        }

        $plan = MaintenancePlan::find($schedule->maintenance_plan_id);

        $schedule->forceFill([
            'rescheduled_from_due_at' => $schedule->due_at,
            'rescheduled_reason' => $reason,
            'due_at' => $newDueAt,
            'grace_until' => $newDueAt->addMinutes($plan?->grace_period_minutes ?? 0),
            'status' => $newDueAt->isPast() ? 'DUE' : 'PLANNED',
        ])->save();

        unset($userId);

        return $schedule->fresh();
    }
}
