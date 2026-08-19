<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\WorkOrderPart;
use App\Modules\WorkOrder\Models\WorkOrder;

/**
 * What the parts on a work order cost (SRS 13.3).
 *
 * The sum of consumed quantity times the issue-time unit cost. Returned stock
 * is excluded: it went back on the shelf, and charging the repair for it would
 * overstate what maintenance actually spent.
 *
 * Derived, never typed in. A parts total that disagrees with the lines
 * underneath it is worse than no total, because somebody makes a
 * repair-versus-replace decision on it (ADR-064).
 */
class WorkOrderPartsCost
{
    public function forWorkOrder(WorkOrder $workOrder): string
    {
        $lines = WorkOrderPart::where('work_order_id', $workOrder->id)
            ->whereNotIn('status', ['CANCELLED'])
            ->get();

        $total = '0.0000';

        foreach ($lines as $line) {
            // Issued minus returned, not consumed: stock still out of the store
            // is stock this job is holding, and it is charged until it comes
            // back. A work order cannot close while that is true anyway.
            $chargeable = bcsub(
                (string) $line->quantity_issued,
                (string) $line->quantity_returned,
                4,
            );

            if (bccomp($chargeable, '0', 4) <= 0) {
                continue;
            }

            $total = bcadd($total, bcmul($chargeable, (string) ($line->unit_cost ?? '0'), 4), 4);
        }

        return $total;
    }
}
