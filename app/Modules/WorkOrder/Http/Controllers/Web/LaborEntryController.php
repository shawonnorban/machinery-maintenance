<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Web;

use App\Modules\WorkOrder\Actions\RecordLaborEntry;
use App\Modules\WorkOrder\Http\Requests\RecordLaborEntryRequest;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderLaborEntry;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LaborEntryController extends Controller
{
    public function __construct(private readonly RecordLaborEntry $action) {}

    public function store(RecordLaborEntryRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $validated = $request->validated();

        $technician = Technician::find($validated['technician_id']);

        $this->action->handle(
            workOrder: $workOrder,
            // On the technician's clock. A shift entered as 09:00-11:00 in
            // Dhaka and stored as UTC would sit six hours away, breaking both
            // the overlap check and any comparison against the work order.
            startedAt: $request->localDateTime('started_at'),
            endedAt: $request->localDateTime('ended_at'),
            technician: $technician,
            notes: $validated['notes'] ?? null,
            userId: $request->user()->id,
        );

        return back()->with('status', __('work_order.labor_recorded'));
    }

    public function destroy(Request $request, WorkOrder $workOrder, string $entry): RedirectResponse
    {
        $this->authorize('work_order.labor.manage');

        // Scoped to the work order in the path, so an entry id from another job
        // cannot be deleted through this route.
        $record = WorkOrderLaborEntry::where('work_order_id', $workOrder->id)
            ->where('id', $entry)
            ->firstOrFail();

        $this->action->delete($record);

        return back()->with('status', __('work_order.labor_deleted'));
    }
}
