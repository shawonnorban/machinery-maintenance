<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Http\Controllers\Web;

use App\Modules\Breakdown\Actions\RaiseBreakdownWorkOrder;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\ProductionImpact;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\TenantTimezone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The breakdown lifecycle over HTTP.
 *
 * Thin: the chain checks and the closure requirements live in the action, so
 * the API enforces exactly the same ones (ADR-066).
 */
class BreakdownTransitionController extends Controller
{
    public function __construct(private readonly TransitionBreakdown $transition) {}

    public function acknowledge(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.acknowledge');

        $this->transition->acknowledge($breakdown, $request->user()->id);

        return back()->with('status', __('breakdown.acknowledged_message'));
    }

    public function assign(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.assign');

        $validated = $request->validate([
            'assigned_technician_id' => ['required', 'string', 'size:26'],
        ]);

        $this->transition->assign($breakdown, $validated['assigned_technician_id'], $request->user()->id);

        return back()->with('status', __('breakdown.assigned_message'));
    }

    public function arrive(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.repair');

        $this->transition->recordArrival($breakdown, $request->user()->id);

        return back()->with('status', __('breakdown.arrival_recorded'));
    }

    public function startRepair(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.repair');

        $this->transition->startRepair($breakdown, $request->user()->id);

        return back()->with('status', __('breakdown.repair_started_message'));
    }

    public function hold(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.repair');

        $validated = $request->validate([
            'reason_code' => ['required', Rule::in(Breakdown::HOLD_REASONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->transition->hold(
            $breakdown,
            $validated['reason_code'],
            $request->user()->id,
            $validated['notes'] ?? null,
        );

        return back()->with('status', __('breakdown.held_message'));
    }

    public function resume(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.repair');

        $this->transition->resume($breakdown, $request->user()->id);

        return back()->with('status', __('breakdown.resumed_message'));
    }

    public function completeRepair(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.repair');

        $this->transition->completeRepair($breakdown, $request->user()->id);

        return back()->with('status', __('breakdown.repair_completed_message'));
    }

    public function resumeProduction(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.repair');

        $this->transition->resumeProduction($breakdown, $request->user()->id);

        return back()->with('status', __('breakdown.production_resumed_message'));
    }

    public function close(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.close');

        $validated = $request->validate([
            // Required here as well as in the action. Closing without a cause is
            // the single thing that would make the failure-analysis reports
            // produce nothing (ERD Section 10 rule 3).
            'failure_code_id' => ['required', 'string', 'size:26'],
            'root_cause_id' => ['required', 'string', 'size:26'],
            'failure_category_id' => ['nullable', 'string', 'size:26'],
            'corrective_action' => ['nullable', 'string', 'max:5000'],
            'preventive_action' => ['nullable', 'string', 'max:5000'],
            'closure_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->transition->close($breakdown, $validated, $request->user()->id);

        return back()->with('status', __('breakdown.closed_message'));
    }

    public function cancel(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.close');

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:255'],
        ]);

        $this->transition->cancel($breakdown, $validated['cancellation_reason'], $request->user()->id);

        return back()->with('status', __('breakdown.cancelled_message'));
    }

    public function raiseWorkOrder(
        Request $request,
        Breakdown $breakdown,
        RaiseBreakdownWorkOrder $action,
    ): RedirectResponse {
        $this->authorize('work_order.work_order.create');

        $workOrder = $action->handle($breakdown, $request->user()->id);

        return redirect()
            ->route('app.work-orders.show', $workOrder)
            ->with('status', __('breakdown.work_order_raised', [
                'number' => $workOrder->work_order_number,
            ]));
    }

    public function recordImpact(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.repair');

        $validated = $request->validate([
            'production_line_id' => ['nullable', 'string', 'size:26'],
            'production_order_reference' => ['nullable', 'string', 'max:255'],
            // Pieces, not money: converting to currency needs a rate this
            // system does not own.
            'estimated_loss' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'actual_loss' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        ProductionImpact::create([
            'breakdown_id' => $breakdown->id,
            'production_line_id' => filled($validated['production_line_id'] ?? null)
                ? $validated['production_line_id']
                : $breakdown->production_line_id,
            'production_order_reference' => $validated['production_order_reference'] ?? null,
            'estimated_loss' => $validated['estimated_loss'] ?? null,
            'actual_loss' => $validated['actual_loss'] ?? null,
            'unit' => 'PIECES',
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('status', __('breakdown.impact_recorded'));
    }

    /**
     * Backdates a chain timestamp. A machine that stopped at 21:50 and was
     * reported at 06:10 next morning is a real and common case, and forcing
     * "now" onto every stamp would make the whole chain fiction.
     */
    public function correctTimestamp(Request $request, Breakdown $breakdown): RedirectResponse
    {
        $this->authorize('breakdown.breakdown.repair');

        $validated = $request->validate([
            'field' => ['required', Rule::in(Breakdown::TIMESTAMP_CHAIN)],
            'value' => ['required', 'date'],
        ]);

        $this->transition->correctTimestamp(
            $breakdown,
            $validated['field'],
            // Read on the factory's clock, not the server's. The field the
            // technician typed into has no timezone in it at all.
            app(TenantTimezone::class)->toUtc($validated['value']),
            $request->user()->id,
        );

        return back()->with('status', __('breakdown.timestamp_corrected'));
    }
}
