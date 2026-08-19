<?php

declare(strict_types=1);

namespace App\Modules\Approval\Http\Controllers\Web;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(private readonly DecideApproval $decisions) {}

    public function index(Request $request): View
    {
        $this->authorize('approval.request.approve');

        $status = $request->query('status', 'PENDING');
        $user = $request->user();

        $requests = ApprovalRequest::query()
            ->with('workflow')
            ->when($status !== 'ALL', fn ($q) => $q->where('status', $status))
            ->orderByDesc('requested_at')
            ->paginate(min(max((int) $request->query('per_page', 25), 10), 100))
            ->withQueryString();

        // Which of these the signed-in user can actually act on. Rendering an
        // approve button that returns 403 teaches people to distrust the
        // screen (Frontend 3.4).
        $actionable = $requests->getCollection()
            ->filter(fn (ApprovalRequest $r) => $this->decisions->canAct($r, $user))
            ->pluck('id')
            ->all();

        return view('approval::approvals.index', [
            'requests' => $requests,
            'status' => $status,
            'actionable' => $actionable,
            'workOrders' => $this->workOrdersFor($requests->getCollection()),
            'counts' => [
                'pending' => ApprovalRequest::where('status', 'PENDING')->count(),
                'mine' => ApprovalRequest::where('status', 'PENDING')->get()
                    ->filter(fn (ApprovalRequest $r) => $this->decisions->canAct($r, $user))
                    ->count(),
            ],
        ]);
    }

    public function show(Request $request, ApprovalRequest $approval): View
    {
        $this->authorize('approval.request.approve');

        $approval->load(['workflow', 'actions']);

        return view('approval::approvals.show', [
            'request' => $approval,
            'rules' => $approval->applicableRules(),
            'canAct' => $this->decisions->canAct($approval, $request->user()),
            'workOrder' => $approval->entity_type === 'WORK_ORDER'
                ? WorkOrder::find($approval->entity_id)
                : null,
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('approval.request.approve');

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->decisions->approve($approval, $request->user(), $validated['comment'] ?? null);

        // Says who it now needs, rather than leaving the approver wondering
        // whether anything else has to happen.
        $message = $result->isPending()
            ? __('approval.advanced_to_next', [
                'name' => $result->currentRule()?->name ?? __('approval.approver'),
            ])
            : __('approval.approved_message');

        return back()->with('status', $message);
    }

    public function reject(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('approval.request.reject');

        $validated = $request->validate([
            // Required here as well as in the action: a refusal with no reason
            // gives the requester nothing to act on.
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $this->decisions->reject($approval, $request->user(), $validated['comment']);

        return back()->with('status', __('approval.rejected_message'));
    }

    /**
     * @param  Collection<int, ApprovalRequest>  $requests
     * @return Collection<string, WorkOrder>
     */
    private function workOrdersFor(Collection $requests): Collection
    {
        $ids = $requests->where('entity_type', 'WORK_ORDER')->pluck('entity_id');

        return WorkOrder::whereIn('id', $ids)
            ->with('asset:id,asset_code,name')
            ->get()
            ->keyBy('id');
    }
}
