<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Inventory\Models\WorkOrderPart;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What the floor is waiting for (SRS 22).
 *
 * A request made on a work order is useless if the only person who ever sees
 * it is the technician who made it. This is the store's side of it: every part
 * asked for and not yet handed over, with what is on the shelf beside it, so a
 * storekeeper can tell in one screen what to prepare and what to go and buy.
 *
 * Sorted by how long the job has been waiting rather than by when the request
 * was made — a critical machine stopped since this morning outranks a request
 * typed an hour earlier for a spare that is merely running low.
 */
class PartRequestController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): View
    {
        $this->authorize('inventory.part.view_any');

        $factoryIds = $this->context->accessibleFactoryIds();

        $openWorkOrderIds = WorkOrder::query()
            ->whereIn('factory_id', $factoryIds)
            ->whereIn('status', WorkOrder::OPEN_STATUSES)
            ->pluck('id');

        $lines = WorkOrderPart::query()
            ->with([
                'sparePart:id,part_number,name,unit,reorder_level',
                'workOrder:id,work_order_number,title,priority,status,asset_id,factory_id,created_at',
                'workOrder.asset:id,asset_code,name,criticality',
            ])
            ->where('status', 'REQUESTED')
            ->whereIn('work_order_id', $openWorkOrderIds)
            ->get()
            // A stopped machine first, then the oldest wait. Ordering this in
            // PHP rather than SQL because the priority lives on the work order
            // and the wait on the request, and the join to sort on both would
            // be harder to read than this is.
            ->sortBy([
                fn (WorkOrderPart $line) => array_search(
                    $line->workOrder?->priority,
                    ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'],
                    true,
                ) ?: 9,
                fn (WorkOrderPart $line) => $line->created_at?->getTimestamp() ?? 0,
            ])
            ->values();

        return view('inventory::requests.index', [
            'lines' => $lines,
            'canIssue' => $request->user()->can('inventory.stock.issue'),
        ]);
    }
}
