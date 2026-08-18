<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Http\Controllers\Web;

use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Every work order state change over HTTP.
 *
 * Thin on purpose: the guards live in TransitionWorkOrder so the API gets
 * exactly the same ones (ADR-066). A rule enforced in a controller is a rule
 * the mobile app does not have.
 */
class WorkOrderTransitionController extends Controller
{
    public function __construct(
        private readonly TransitionWorkOrder $transition,
        private readonly AssignTechnicians $assign,
    ) {}

    public function schedule(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.update');

        // DRAFT to SCHEDULED is the act of committing the job to the queue.
        $this->transition->schedule($workOrder, $request->user()->id);

        return back()->with('status', __('work_order.scheduled'));
    }

    public function assign(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.assign');

        $validated = $request->validate([
            'technician_ids' => ['required', 'array', 'min:1'],
            'technician_ids.*' => ['string', 'size:26'],
        ]);

        $this->assign->handle($workOrder, $validated['technician_ids'], $request->user()->id);

        return back()->with('status', __('work_order.assigned'));
    }

    public function unassign(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.assign');

        $validated = $request->validate([
            'technician_id' => ['required', 'string', 'size:26'],
        ]);

        $this->assign->unassign($workOrder, $validated['technician_id'], $request->user()->id);

        return back()->with('status', __('work_order.unassigned'));
    }

    public function start(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.start');

        $this->transition->start($workOrder, $request->user()->id);

        return back()->with('status', __('work_order.started'));
    }

    public function hold(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.start');

        $validated = $request->validate([
            // A reason code, not free text: AWAITING_PARTS is what makes a
            // spare-part shortage visible as its own cause instead of
            // inflating repair time (ADR-051).
            'reason_code' => ['required', Rule::in(WorkOrder::HOLD_REASONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->transition->hold(
            $workOrder,
            $validated['reason_code'],
            $request->user()->id,
            $validated['notes'] ?? null,
        );

        return back()->with('status', __('work_order.held'));
    }

    public function resume(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.start');

        $this->transition->resume($workOrder, $request->user()->id);

        return back()->with('status', __('work_order.resumed'));
    }

    public function complete(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.complete');

        $this->transition->complete($workOrder, $request->user()->id);

        return back()->with('status', __('work_order.completed'));
    }

    public function verify(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.verify');

        $this->transition->verify($workOrder, $request->user()->id);

        return back()->with('status', __('work_order.verified'));
    }

    public function close(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.close');

        $this->transition->close($workOrder, $request->user()->id);

        return back()->with('status', __('work_order.closed'));
    }

    public function cancel(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.cancel');

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:255'],
        ]);

        $this->transition->cancel($workOrder, $request->user()->id, $validated['cancellation_reason']);

        return back()->with('status', __('work_order.cancelled'));
    }

    public function reopen(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('work_order.work_order.reopen');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->transition->reopen($workOrder, $request->user()->id, $validated['reason']);

        return back()->with('status', __('work_order.reopened'));
    }
}
