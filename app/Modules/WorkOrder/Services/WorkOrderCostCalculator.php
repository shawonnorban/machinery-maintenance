<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Services;

use App\Modules\Costing\Services\CostPoster;
use App\Modules\Inventory\Services\WorkOrderPartsCost;
use App\Modules\WorkOrder\Models\WorkOrder;

/**
 * Derives a work order's actual cost from its own records (ADR-064).
 *
 * Never accepted from a client. A total that disagrees with the labour entries
 * and part lines underneath it is worse than having no total, because someone
 * will make a repair-versus-replace decision on it.
 *
 * Arithmetic is done with bcmath rather than floats. Adding a few hundred lines
 * of 0.1 in binary floating point does not give what a storekeeper gets on
 * paper, and a cost report that is a few paisa out per row is one somebody has
 * to reconcile by hand (ADR-063).
 */
class WorkOrderCostCalculator
{
    private const SCALE = 4;

    public function __construct(
        private readonly WorkOrderPartsCost $partsCost,
        private readonly CostPoster $costs,
    ) {}

    public function recalculate(WorkOrder $workOrder): WorkOrder
    {
        // Labour is absent on purpose: technicians are salaried, so their
        // hours are already paid for and charging them here would invent a
        // number no ledger in the business agrees with.
        //
        // Consumed and unreturned parts at their issue-time cost. Derived from
        // the part lines, which are themselves derived from the ledger, so the
        // number can always be traced back to a movement.
        $parts = $this->partsCost->forWorkOrder($workOrder);
        $other = $this->money($workOrder->actual_other_cost);

        $workOrder->forceFill([
            'actual_parts_cost' => $parts,
            'actual_other_cost' => $other,
            'actual_cost' => bcadd($parts, $other, self::SCALE),
        ])->save();

        // The same figures, projected into the cost ledger so a machine's
        // lifetime cost is assembled from posted entries rather than by summing
        // work orders at report time.
        $this->costs->syncWorkOrder($workOrder->fresh());

        return $workOrder->fresh();
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), self::SCALE, '.', '');
    }
}
