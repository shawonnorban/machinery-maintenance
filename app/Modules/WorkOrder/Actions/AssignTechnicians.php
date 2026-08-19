<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Actions;

use App\Modules\Notification\Services\MaintenanceNotifier;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Puts technicians on a work order (SRS 13.2).
 *
 * Assignments are ended rather than deleted, so "who was on this job" stays
 * answerable after a reassignment. A closed work order that shows only the last
 * technician hides the handover, which is usually the interesting part.
 */
class AssignTechnicians
{
    public function __construct(
        private readonly TransitionWorkOrder $transition,
        private readonly MaintenanceNotifier $notifier,
    ) {}

    /**
     * @param  list<string>  $technicianIds
     */
    public function handle(WorkOrder $workOrder, array $technicianIds, ?string $userId = null): WorkOrder
    {
        if ($workOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'work_order_id' => __('work_order.assign_after_close'),
            ])->status(409);
        }

        $technicianIds = array_values(array_unique(array_filter($technicianIds)));

        if ($technicianIds === []) {
            throw ValidationException::withMessages([
                'technician_ids' => __('work_order.assign_needs_technician'),
            ]);
        }

        $technicians = Technician::whereIn('id', $technicianIds)->get();

        if ($technicians->count() !== count($technicianIds)) {
            throw ValidationException::withMessages([
                'technician_ids' => __('work_order.technician_not_found'),
            ]);
        }

        foreach ($technicians as $technician) {
            $this->assertAssignable($workOrder, $technician);
        }

        return DB::transaction(function () use ($workOrder, $technicians, $userId): WorkOrder {
            $now = CarbonImmutable::now();
            $keep = $technicians->pluck('id')->all();

            // Anyone dropped from the list is ended, not removed.
            WorkOrderAssignment::where('work_order_id', $workOrder->id)
                ->whereNull('unassigned_at')
                ->whereNotIn('technician_id', $keep)
                ->get()
                ->each(fn (WorkOrderAssignment $a) => $a->forceFill(['unassigned_at' => $now])->save());

            foreach ($technicians as $technician) {
                $existing = WorkOrderAssignment::where('work_order_id', $workOrder->id)
                    ->where('technician_id', $technician->id)
                    ->whereNull('unassigned_at')
                    ->first();

                if ($existing !== null) {
                    continue;
                }

                WorkOrderAssignment::create([
                    'work_order_id' => $workOrder->id,
                    'technician_id' => $technician->id,
                    'assigned_by' => $userId,
                    'assigned_at' => $now,
                ]);

                // The person who has to do the work is the person who needs to
                // know, so this goes to the technician rather than their
                // manager.
                $this->notifier->workOrderAssigned($workOrder, $technician);
            }

            $workOrder = $workOrder->fresh();

            // SCHEDULED is the only state where naming people also advances the
            // job. Adding someone to work already in progress is a reinforcement,
            // not a status change.
            if ($workOrder->status === 'SCHEDULED') {
                $workOrder = $this->transition->assign($workOrder, $userId ?? '');
            }

            return $workOrder->fresh();
        });
    }

    public function unassign(WorkOrder $workOrder, string $technicianId, ?string $userId = null): WorkOrder
    {
        $assignment = WorkOrderAssignment::where('work_order_id', $workOrder->id)
            ->where('technician_id', $technicianId)
            ->whereNull('unassigned_at')
            ->first();

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'technician_id' => __('work_order.not_assigned'),
            ]);
        }

        return DB::transaction(function () use ($workOrder, $assignment, $userId): WorkOrder {
            $assignment->forceFill(['unassigned_at' => CarbonImmutable::now()])->save();

            $workOrder = $workOrder->fresh();

            // An assigned job with nobody on it is a job nobody will do, so it
            // goes back to the queue rather than sitting in ASSIGNED unmanned.
            if ($workOrder->status === 'ASSIGNED' && $workOrder->activeAssignments()->count() === 0) {
                $workOrder = $this->transition->unassignAll($workOrder, $userId ?? '');
            }

            return $workOrder->fresh();
        });
    }

    private function assertAssignable(WorkOrder $workOrder, Technician $technician): void
    {
        if (! $technician->isActive()) {
            throw ValidationException::withMessages([
                'technician_ids' => __('work_order.technician_inactive', ['name' => $technician->name]),
            ]);
        }

        if ($technician->factory_id !== $workOrder->factory_id) {
            // Sending someone to another site is a real decision with travel
            // attached; it is not something an assignment dropdown should do
            // by accident.
            throw ValidationException::withMessages([
                'technician_ids' => __('work_order.technician_other_factory', ['name' => $technician->name]),
            ]);
        }

        $limit = $technician->max_concurrent_work_orders;

        if ($limit === null) {
            return;
        }

        $openCount = WorkOrderAssignment::query()
            ->where('technician_id', $technician->id)
            ->whereNull('unassigned_at')
            ->where('work_order_id', '!=', $workOrder->id)
            ->whereIn('work_order_id', WorkOrder::query()
                ->whereIn('status', WorkOrder::OPEN_STATUSES)
                ->select('id'))
            ->count();

        if ($openCount >= $limit) {
            // A queue of twenty jobs against one person is a planning fiction.
            // It reads as scheduled work while none of it is actually moving.
            throw ValidationException::withMessages([
                'technician_ids' => __('work_order.technician_at_capacity', [
                    'name' => $technician->name,
                    'limit' => $limit,
                ]),
            ])->status(409);
        }
    }
}
