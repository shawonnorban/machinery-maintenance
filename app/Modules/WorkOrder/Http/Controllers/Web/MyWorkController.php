<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Web;

use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderAssignment;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A technician's own queue (Frontend 6.1).
 *
 * Rendered on the mobile layout, not the desktop one: this is the screen used
 * one-handed, in gloves, next to a running machine, and it keeps the 44px
 * targets and 16px type the admin density pass deliberately opts out of.
 */
class MyWorkController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('work_order.work_order.view_any');

        $technician = Technician::where('user_id', $request->user()->id)->first();

        $workOrders = collect();

        if ($technician !== null) {
            $assignedIds = WorkOrderAssignment::where('technician_id', $technician->id)
                ->whereNull('unassigned_at')
                ->pluck('work_order_id');

            $workOrders = WorkOrder::query()
                ->with(['asset:id,asset_code,name', 'maintenanceType:id,name'])
                ->whereIn('id', $assignedIds)
                ->whereIn('status', WorkOrder::OPEN_STATUSES)
                // In-progress work first: it is the job in their hands right
                // now, and it should not be below tomorrow's list.
                ->orderByRaw("FIELD(status, 'IN_PROGRESS', 'ON_HOLD', 'ASSIGNED', 'SCHEDULED')")
                ->orderByRaw("FIELD(priority, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW')")
                ->orderByRaw('scheduled_start IS NULL, scheduled_start ASC')
                ->get();
        }

        return view('work_order::my-work.index', [
            'technician' => $technician,
            'workOrders' => $workOrders,
        ]);
    }
}
