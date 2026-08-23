<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Modules\Inventory\Actions\IssuePartsToWorkOrder;
use App\Modules\Inventory\Actions\ReserveStock;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\SparePartReservation;
use App\Modules\Inventory\Models\WorkOrderPart;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Services\WorkOrderCostCalculator;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Parts on a work order over HTTP.
 *
 * The cost recalculation runs after every movement, so the work order's parts
 * total can never drift from the lines underneath it (ADR-064).
 */
class WorkOrderPartsController extends Controller
{
    public function __construct(
        private readonly IssuePartsToWorkOrder $parts,
        private readonly ReserveStock $reservations,
        private readonly WorkOrderCostCalculator $costs,
    ) {}

    /**
     * A technician asks for a part.
     *
     * The step that was missing between "this machine needs a hook" and "the
     * store handed one over". Without it the only record of a part being
     * needed was the moment it was issued, so a part nobody had in stock left
     * no trace at all — the job simply sat there, and the reason lived in
     * somebody's memory.
     *
     * It moves no stock and reserves nothing. It is a request, and the store
     * decides what to do with it.
     */
    public function request(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.part.request');

        $validated = $request->validate([
            'spare_part_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->parts->request(
            $workOrder,
            SparePart::findOrFail($validated['spare_part_id']),
            (string) $validated['quantity'],
        );

        return back()->with('status', __('inventory.requested_message'));
    }

    /**
     * Withdraw a request that is no longer needed.
     *
     * Only while nothing has been issued against it: once the store has handed
     * parts over, the line is a record of stock that moved, and cancelling it
     * would leave those units unaccounted for.
     */
    public function cancelRequest(Request $request, WorkOrder $workOrder, string $line): RedirectResponse
    {
        $this->authorize('work_order.part.request');

        $record = WorkOrderPart::where('work_order_id', $workOrder->id)
            ->where('id', $line)
            ->firstOrFail();

        if (bccomp((string) $record->quantity_issued, '0', 4) === 1) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.cannot_cancel_issued'),
            ])->status(409);
        }

        $record->forceFill(['status' => 'CANCELLED'])->save();

        return back()->with('status', __('inventory.request_cancelled'));
    }

    public function reserve(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('inventory.reservation.manage');

        $validated = $request->validate([
            'spare_part_id' => ['required', 'string', 'size:26'],
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->reservations->handle(
            SparePart::findOrFail($validated['spare_part_id']),
            Bin::findOrFail($validated['bin_id']),
            $workOrder,
            (string) $validated['quantity'],
            $request->user()->id,
        );

        return back()->with('status', __('inventory.reserved_message'));
    }

    public function release(Request $request, WorkOrder $workOrder, string $reservation): RedirectResponse
    {
        $this->authorize('inventory.reservation.manage');

        // Scoped to the work order in the path, so a reservation id belonging
        // to another job cannot be released through this route.
        $record = SparePartReservation::where('work_order_id', $workOrder->id)
            ->where('id', $reservation)
            ->firstOrFail();

        $this->reservations->release($record, null, $request->user()->id);

        return back()->with('status', __('inventory.released'));
    }

    public function issue(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('inventory.stock.issue');

        $validated = $request->validate([
            'spare_part_id' => ['required', 'string', 'size:26'],
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reservation_id' => ['nullable', 'string', 'size:26'],
        ]);

        $reservation = filled($validated['reservation_id'] ?? null)
            ? SparePartReservation::where('work_order_id', $workOrder->id)
                ->where('id', $validated['reservation_id'])
                ->firstOrFail()
            : null;

        $this->parts->issue(
            $workOrder,
            SparePart::findOrFail($validated['spare_part_id']),
            Bin::findOrFail($validated['bin_id']),
            (string) $validated['quantity'],
            $request->user()->id,
            $reservation,
        );

        $this->costs->recalculate($workOrder->fresh());

        return back()->with('status', __('inventory.issued'));
    }

    public function consume(Request $request, WorkOrder $workOrder, string $line): RedirectResponse
    {
        $this->authorize('inventory.stock.issue');

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->parts->consume(
            $this->lineFor($workOrder, $line),
            (string) $validated['quantity'],
            $request->user()->id,
        );

        $this->costs->recalculate($workOrder->fresh());

        return back()->with('status', __('inventory.consumed'));
    }

    public function returnToStore(Request $request, WorkOrder $workOrder, string $line): RedirectResponse
    {
        $this->authorize('inventory.stock.return');

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $record = $this->lineFor($workOrder, $line);

        if ($record->bin_id === null) {
            throw ValidationException::withMessages([
                'quantity' => __('inventory.receive_needs_destination'),
            ]);
        }

        $this->parts->returnToStore(
            $record,
            (string) $validated['quantity'],
            $request->user()->id,
        );

        $this->costs->recalculate($workOrder->fresh());

        return back()->with('status', __('inventory.returned'));
    }

    private function lineFor(WorkOrder $workOrder, string $line): WorkOrderPart
    {
        return WorkOrderPart::where('work_order_id', $workOrder->id)
            ->where('id', $line)
            ->firstOrFail();
    }
}
