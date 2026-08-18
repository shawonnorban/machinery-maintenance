<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Actions;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Models\Asset;
use App\Modules\Maintenance\Actions\CompleteSchedule;
use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderHold;
use App\Modules\WorkOrder\Models\WorkOrderStatusHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every work order state change (SRS 13.1, Data Dictionary 3.1).
 *
 * One place, because the guards are the product: completing with unanswered
 * required checks, or verifying your own work, are exactly the shortcuts that
 * make a maintenance record worthless in an audit.
 */
class TransitionWorkOrder
{
    public function __construct(
        private readonly ChangeAssetStatus $assetStatus,
        private readonly CompleteSchedule $completeSchedule,
    ) {}

    public function assign(WorkOrder $workOrder, string $userId): WorkOrder
    {
        if ($workOrder->activeAssignments()->count() === 0) {
            throw ValidationException::withMessages([
                'technician_ids' => __('work_order.assign_needs_technician'),
            ]);
        }

        return $this->move($workOrder, 'ASSIGNED', $userId);
    }

    public function unassignAll(WorkOrder $workOrder, string $userId): WorkOrder
    {
        return $this->move($workOrder, 'SCHEDULED', $userId);
    }

    public function start(WorkOrder $workOrder, string $userId, ?CarbonImmutable $at = null): WorkOrder
    {
        $at ??= CarbonImmutable::now();

        return DB::transaction(function () use ($workOrder, $userId, $at): WorkOrder {
            $workOrder = $this->move($workOrder, 'IN_PROGRESS', $userId);

            if ($workOrder->actual_start === null) {
                $workOrder->forceFill(['actual_start' => $at])->save();
            }

            // A shutdown job stops the machine, and the asset record should say
            // so while it is stopped rather than after the fact.
            if ($workOrder->requires_shutdown) {
                $asset = Asset::find($workOrder->asset_id);

                if ($asset !== null && $asset->canTransitionTo('UNDER_MAINTENANCE')) {
                    $this->assetStatus->handle(
                        $asset, 'UNDER_MAINTENANCE', $userId,
                        "Work order {$workOrder->work_order_number} started",
                        'WORK_ORDER',
                    );
                }
            }

            return $workOrder->fresh();
        });
    }

    public function hold(WorkOrder $workOrder, string $reasonCode, string $userId, ?string $notes = null): WorkOrder
    {
        if (! in_array($reasonCode, WorkOrder::HOLD_REASONS, true)) {
            throw ValidationException::withMessages([
                'reason_code' => __('work_order.hold_reason_unknown'),
            ]);
        }

        return DB::transaction(function () use ($workOrder, $reasonCode, $userId, $notes): WorkOrder {
            $workOrder = $this->move($workOrder, 'ON_HOLD', $userId, $reasonCode);

            $now = CarbonImmutable::now();

            WorkOrderHold::create([
                'work_order_id' => $workOrder->id,
                'reason_code' => $reasonCode,
                'notes' => $notes,
                'started_at' => $now,
                'created_by' => $userId,
            ]);

            $workOrder->forceFill([
                'on_hold_since' => $now,
                'hold_reason_code' => $reasonCode,
            ])->save();

            return $workOrder->fresh();
        });
    }

    public function resume(WorkOrder $workOrder, string $userId): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $userId): WorkOrder {
            $now = CarbonImmutable::now();

            $hold = WorkOrderHold::where('work_order_id', $workOrder->id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->first();

            $minutes = 0;

            if ($hold !== null) {
                $minutes = (int) round($hold->started_at->diffInMinutes($now, absolute: true));
                $hold->forceFill(['ended_at' => $now, 'minutes' => $minutes])->save();
            }

            $workOrder = $this->move($workOrder, 'IN_PROGRESS', $userId);

            // Accumulated so repair time can exclude it (ADR-051).
            $workOrder->forceFill([
                'hold_minutes' => $workOrder->hold_minutes + $minutes,
                'on_hold_since' => null,
                'hold_reason_code' => null,
            ])->save();

            return $workOrder->fresh();
        });
    }

    public function complete(WorkOrder $workOrder, string $userId, ?CarbonImmutable $at = null): WorkOrder
    {
        $progress = $workOrder->checklistProgress();

        if ($progress['required_remaining'] > 0) {
            throw ValidationException::withMessages([
                'checklist' => __('work_order.checklist_incomplete', [
                    'count' => $progress['required_remaining'],
                ]),
            ])->status(422);
        }

        $at ??= CarbonImmutable::now();

        return DB::transaction(function () use ($workOrder, $userId, $at): WorkOrder {
            $workOrder = $this->move($workOrder, 'COMPLETED', $userId);

            $workOrder->forceFill([
                'actual_end' => $at,
                'completed_by' => $userId,
                'completed_at' => $at,
            ])->save();

            // The machine goes back to work when the job stops, not when the
            // paperwork is signed off days later.
            if ($workOrder->requires_shutdown) {
                $asset = Asset::find($workOrder->asset_id);

                if ($asset !== null && $asset->canTransitionTo('RUNNING')) {
                    $this->assetStatus->handle(
                        $asset, 'RUNNING', $userId,
                        "Work order {$workOrder->work_order_number} completed",
                        'WORK_ORDER',
                    );
                }
            }

            $this->closeSchedule($workOrder, $at);

            return $workOrder->fresh();
        });
    }

    public function verify(WorkOrder $workOrder, string $userId): WorkOrder
    {
        if (! $workOrder->requires_verification) {
            throw ValidationException::withMessages([
                'status' => __('work_order.verification_not_required'),
            ])->status(409);
        }

        if ($workOrder->completed_by === $userId) {
            // Verification exists to have a second pair of eyes. Signing off
            // your own work makes it ceremony (SRS 13.1).
            throw ValidationException::withMessages([
                'verified_by' => __('work_order.cannot_verify_own_work'),
            ])->status(403);
        }

        return DB::transaction(function () use ($workOrder, $userId): WorkOrder {
            $workOrder = $this->move($workOrder, 'VERIFIED', $userId);

            $workOrder->forceFill([
                'verified_by' => $userId,
                'verified_at' => CarbonImmutable::now(),
            ])->save();

            return $workOrder->fresh();
        });
    }

    public function close(WorkOrder $workOrder, string $userId): WorkOrder
    {
        if ($workOrder->approval_status === 'PENDING') {
            throw ValidationException::withMessages([
                'status' => __('work_order.approval_pending'),
            ])->status(409);
        }

        if ($workOrder->requires_verification && $workOrder->status !== 'VERIFIED') {
            throw ValidationException::withMessages([
                'status' => __('work_order.verification_required'),
            ])->status(422);
        }

        return DB::transaction(function () use ($workOrder, $userId): WorkOrder {
            $workOrder = $this->move($workOrder, 'CLOSED', $userId);

            $workOrder->forceFill([
                'closed_by' => $userId,
                'closed_at' => CarbonImmutable::now(),
            ])->save();

            return $workOrder->fresh();
        });
    }

    public function cancel(WorkOrder $workOrder, string $userId, string $reason): WorkOrder
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'cancellation_reason' => __('work_order.cancel_needs_reason'),
            ]);
        }

        return DB::transaction(function () use ($workOrder, $userId, $reason): WorkOrder {
            $workOrder = $this->move($workOrder, 'CANCELLED', $userId, $reason);

            $workOrder->forceFill([
                'cancelled_by' => $userId,
                'cancelled_at' => CarbonImmutable::now(),
                'cancellation_reason' => $reason,
            ])->save();

            return $workOrder->fresh();
        });
    }

    /**
     * Reopening a completed job is an exception, so it is counted and
     * recorded: a high reopen rate is itself a maintenance-quality signal.
     */
    public function reopen(WorkOrder $workOrder, string $userId, string $reason): WorkOrder
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => __('work_order.reopen_needs_reason'),
            ]);
        }

        return DB::transaction(function () use ($workOrder, $userId, $reason): WorkOrder {
            $workOrder = $this->move($workOrder, 'IN_PROGRESS', $userId, $reason);

            $workOrder->forceFill([
                'reopened_count' => $workOrder->reopened_count + 1,
                'completed_by' => null,
                'completed_at' => null,
                'actual_end' => null,
                'verified_by' => null,
                'verified_at' => null,
            ])->save();

            return $workOrder->fresh();
        });
    }

    /**
     * The single guarded transition. Everything above funnels through here so
     * no path can bypass the table.
     */
    private function move(WorkOrder $workOrder, string $to, ?string $userId, ?string $reason = null): WorkOrder
    {
        $from = $workOrder->status;

        if (! $workOrder->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => __('work_order.invalid_transition', ['from' => $from, 'to' => $to]),
            ])->status(409);
        }

        $workOrder->forceFill([
            'status' => $to,
            'version' => $workOrder->version + 1,
        ])->save();

        WorkOrderStatusHistory::create([
            'work_order_id' => $workOrder->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'changed_at' => CarbonImmutable::now(),
            'reason' => $reason,
        ]);

        return $workOrder;
    }

    /**
     * Completing the work completes the maintenance occurrence that raised it,
     * which is what advances a rolling plan to its next due date.
     */
    private function closeSchedule(WorkOrder $workOrder, CarbonImmutable $at): void
    {
        if ($workOrder->maintenance_schedule_id === null) {
            return;
        }

        $schedule = MaintenanceSchedule::find($workOrder->maintenance_schedule_id);

        if ($schedule !== null && $schedule->isOpen()) {
            $this->completeSchedule->handle($schedule, $at);
        }
    }
}
