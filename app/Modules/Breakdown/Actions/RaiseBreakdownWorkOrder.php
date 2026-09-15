<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Actions;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Support\Sql;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Raises the repair work a breakdown calls for (ERD Section 10).
 *
 * One breakdown may generate many work orders over its lifetime — a second
 * repair pass after the first one closes is real — but never *two open ones
 * at once*: a repeat click on "raise work order" while the first is still
 * DRAFT/IN_PROGRESS/etc. is a double-submit, not a genuine second job, and
 * without this guard it silently created a fresh work order every time
 * (found live: three clicks, three work orders, one breakdown). A work
 * order belongs to at most one breakdown — `work_orders.breakdown_id` is
 * the single link, v1.0 had both that column and a pivot table, and two
 * ways to express one relationship guarantee they eventually disagree.
 */
class RaiseBreakdownWorkOrder
{
    public function __construct(
        private readonly CreateWorkOrder $createWorkOrder,
        private readonly AssignTechnicians $assignTechnicians,
        private readonly TransitionWorkOrder $workOrderTransition,
    ) {}

    public function handle(Breakdown $breakdown, ?string $userId = null): WorkOrder
    {
        if ($breakdown->isTerminal()) {
            throw ValidationException::withMessages([
                'breakdown_id' => __('breakdown.work_order_after_close'),
            ])->status(409);
        }

        $open = $breakdown->workOrders()->whereNotIn('status', WorkOrder::TERMINAL_STATUSES)->first();

        if ($open !== null) {
            throw ValidationException::withMessages([
                'breakdown_id' => __('breakdown.work_order_already_open', ['number' => $open->work_order_number]),
            ])->status(409);
        }

        $workOrder = DB::transaction(function () use ($breakdown, $userId): WorkOrder {
            return $this->createWorkOrder->handle([
                'asset_id' => $breakdown->asset_id,
                'maintenance_type_id' => $this->correctiveTypeId($breakdown),
                'breakdown_id' => $breakdown->id,
                'title' => __('breakdown.work_order_title', [
                    'number' => $breakdown->breakdown_number,
                ]),
                'description' => $breakdown->problem_description,
                'priority' => $breakdown->priority,
                'source' => 'BREAKDOWN',
                // The machine is already stopped. Recording the job as requiring
                // shutdown would double-count the stoppage as planned downtime
                // on top of the unplanned downtime the breakdown already owns
                // (ADR-049).
                'requires_shutdown' => false,
            ], $userId);
        });

        $this->assignRoster($breakdown, $workOrder, $userId);

        return $workOrder->fresh();
    }

    /**
     * Puts the line's standing roster on the job the moment it's raised —
     * whoever is pre-assigned to this line/department (set once, ahead of
     * time, on the Technician's own record — not picked per breakdown) all
     * land on the work order together, so any of them can open it and
     * start. No manager has to hand-pick someone; that manual "Assign" flow
     * remains only as the 2am override for a breakdown outside anyone's
     * normal line (`BreakdownScopeGuard`'s own exemption).
     *
     * A factory-wide technician (no line/department of their own) is
     * deliberately excluded — the same reasoning `MaintenanceNotifier::
     * coveringTechnicianUsers()` uses: breadth like that is for sorting a
     * pick-list, not for deciding who is put straight to work.
     *
     * Guarded like the notifier's own dispatches: an empty roster (nobody
     * covers this line yet) must not block the work order from existing —
     * the manual "Assign" override remains available to put someone on it.
     */
    private function assignRoster(Breakdown $breakdown, WorkOrder $workOrder, ?string $userId): void
    {
        try {
            $location = $breakdown->asset?->location;
            $lineId = $location?->production_line_id;
            $departmentId = $location?->department_id;

            if ($lineId === null && $departmentId === null) {
                return;
            }

            $roster = Technician::where('factory_id', $breakdown->factory_id)
                ->where('status', 'ACTIVE')
                ->where(function ($query) use ($lineId, $departmentId): void {
                    // Line match takes priority — a technician named to a
                    // specific line is never pulled in by a department-wide
                    // match instead, the same priority `coversLocation()`
                    // itself uses.
                    if ($lineId !== null) {
                        $query->where('production_line_id', $lineId);
                    }

                    if ($departmentId !== null) {
                        $query->orWhere(function ($query) use ($departmentId): void {
                            $query->whereNull('production_line_id')
                                ->where('department_id', $departmentId);
                        });
                    }
                })
                ->get();

            if ($roster->isEmpty()) {
                return;
            }

            $workOrder = $this->workOrderTransition->schedule($workOrder->fresh(), $userId ?? '');
            $this->assignTechnicians->handle($workOrder, $roster->pluck('id')->all(), $userId);
        } catch (\Throwable $e) {
            Log::warning('Auto-assigning a breakdown work order to its line roster failed', [
                'breakdown_id' => $breakdown->id,
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Corrective, falling back to the first available type. A work order cannot
     * exist without a maintenance type, and refusing to raise repair work
     * because the taxonomy is incomplete would be the worse failure of the two.
     */
    private function correctiveTypeId(Breakdown $breakdown): string
    {
        $available = MaintenanceType::query()
            ->availableTo($breakdown->company_id)
            ->where('active', true);

        $type = (clone $available)->whereIn('code', ['CORRECTIVE', 'EMERGENCY'])
            // CORRECTIVE first, EMERGENCY as the fallback of the two.
            ->orderByRaw(Sql::orderByList('code', ['CORRECTIVE', 'EMERGENCY']))
            ->first()
            ?? $available->orderBy('name')->first();

        if ($type === null) {
            throw ValidationException::withMessages([
                'maintenance_type_id' => __('breakdown.no_maintenance_type'),
            ]);
        }

        return $type->id;
    }
}
