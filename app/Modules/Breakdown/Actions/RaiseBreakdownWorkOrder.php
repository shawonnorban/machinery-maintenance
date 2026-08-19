<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Actions;

use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Raises the repair work a breakdown calls for (ERD Section 10).
 *
 * One breakdown may generate many work orders; a work order belongs to at most
 * one breakdown. `work_orders.breakdown_id` is the single link — v1.0 had both
 * that column and a pivot table, and two ways to express one relationship
 * guarantee they eventually disagree.
 */
class RaiseBreakdownWorkOrder
{
    public function __construct(private readonly CreateWorkOrder $createWorkOrder) {}

    public function handle(Breakdown $breakdown, ?string $userId = null): WorkOrder
    {
        if ($breakdown->isTerminal()) {
            throw ValidationException::withMessages([
                'breakdown_id' => __('breakdown.work_order_after_close'),
            ])->status(409);
        }

        return DB::transaction(function () use ($breakdown, $userId): WorkOrder {
            $workOrder = $this->createWorkOrder->handle([
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

            return $workOrder;
        });
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
            ->orderByRaw("FIELD(code, 'CORRECTIVE', 'EMERGENCY')")
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
