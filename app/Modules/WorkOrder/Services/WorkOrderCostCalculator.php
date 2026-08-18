<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Services;

use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderLaborEntry;

/**
 * Derives a work order's actual cost from its own records (ADR-064).
 *
 * Never accepted from a client. A total that disagrees with the labour entries
 * and part lines underneath it is worse than having no total, because someone
 * will make a repair-versus-replace decision on it.
 */
class WorkOrderCostCalculator
{
    public function recalculate(WorkOrder $workOrder): WorkOrder
    {
        $labor = WorkOrderLaborEntry::where('work_order_id', $workOrder->id)
            ->get()
            ->reduce(fn (float $carry, WorkOrderLaborEntry $entry) => $carry + (float) $entry->amount, 0.0);

        // Parts land with the inventory module (build order 19). Until then the
        // line is zero rather than absent, so the total is already correct in
        // shape and only gains a component later.
        $parts = (float) ($workOrder->actual_parts_cost ?? 0);
        $other = (float) ($workOrder->actual_other_cost ?? 0);

        $workOrder->forceFill([
            'actual_labor_cost' => $this->money($labor),
            'actual_parts_cost' => $this->money($parts),
            'actual_other_cost' => $this->money($other),
            'actual_cost' => $this->money($labor + $parts + $other),
        ])->save();

        return $workOrder->fresh();
    }

    private function money(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
