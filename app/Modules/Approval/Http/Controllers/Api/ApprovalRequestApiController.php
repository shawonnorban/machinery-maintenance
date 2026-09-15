<?php

declare(strict_types=1);

namespace App\Modules\Approval\Http\Controllers\Api;

use App\Modules\Approval\Actions\DecideApproval;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Identity\Models\User;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Approvals, over the wire (API 27; mirrors the web `ApprovalController`,
 * which this delegates every decision to unchanged per ADR-003).
 */
class ApprovalRequestApiController extends ApiController
{
    public function __construct(private readonly DecideApproval $decisions) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('approval.request.approve');

        $status = $request->query('status', 'PENDING');
        $viewer = $this->caller()->user;

        $query = ApprovalRequest::query()
            ->with('workflow')
            ->when($status !== 'ALL', fn ($q) => $q->where('status', $status))
            ->orderByDesc('requested_at');

        $paginator = $query->paginate($this->perPage($request))->withQueryString();
        $items = collect($paginator->items());
        $workOrders = $this->workOrdersFor($items);

        // Which of these the signed-in user can actually act on, and how many
        // pending requests are theirs to act on — a client rendering an
        // approve button that would 403 teaches people to distrust the
        // screen (Frontend 3.4), same reasoning as the web index.
        return ApiResponse::ok(
            $items->map(fn (ApprovalRequest $approval): array => $this->summary($approval, $viewer, $workOrders))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'counts' => [
                    'pending' => ApprovalRequest::where('status', 'PENDING')->count(),
                    'mine' => $viewer === null ? 0 : ApprovalRequest::where('status', 'PENDING')->get()
                        ->filter(fn (ApprovalRequest $r) => $this->decisions->canAct($r, $viewer))
                        ->count(),
                ],
            ],
        );
    }

    public function show(ApprovalRequest $approval): JsonResponse
    {
        $this->allow('approval.request.approve');

        $approval->load(['workflow', 'actions']);

        return ApiResponse::ok($this->detail($approval, $this->caller()->user, $approval->applicableRules()));
    }

    public function approve(Request $request, ApprovalRequest $approval): JsonResponse
    {
        $this->allow('approval.request.approve');

        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        return ApiResponse::ok($this->summary(
            $this->decisions->approve($approval, $this->approver(), $data['comment'] ?? null),
        ));
    }

    public function reject(Request $request, ApprovalRequest $approval): JsonResponse
    {
        $this->allow('approval.request.reject');

        $data = $request->validate([
            // Required here as well as in the action: a refusal with no
            // reason gives the requester nothing to act on.
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        return ApiResponse::ok($this->summary(
            $this->decisions->reject($approval, $this->approver(), $data['comment']),
        ));
    }

    /**
     * A decision recorded against nobody is a decision nobody can be asked
     * about later, so a machine caller cannot make one.
     */
    private function approver(): User
    {
        $user = $this->caller()->user;

        if ($user === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return $user;
    }

    /**
     * One query for the whole page rather than one per row — the web index
     * does the same via its own `workOrdersFor()` for the same reason.
     *
     * @param  Collection<int, ApprovalRequest>  $approvals
     * @return Collection<string, WorkOrder>
     */
    private function workOrdersFor(Collection $approvals): Collection
    {
        $ids = $approvals->where('entity_type', 'WORK_ORDER')->pluck('entity_id');

        return WorkOrder::whereIn('id', $ids)->with('asset:id,asset_code')->get()->keyBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ApprovalRequest $approval, ?User $viewer = null, ?Collection $workOrders = null): array
    {
        $workOrder = $approval->entity_type === 'WORK_ORDER'
            ? ($workOrders?->get($approval->entity_id) ?? WorkOrder::with('asset:id,asset_code')->find($approval->entity_id))
            : null;

        return [
            'id' => $approval->id,
            'workflow' => $approval->workflow === null ? null : ['id' => $approval->workflow->id, 'name' => $approval->workflow->name],
            'entity_type' => $approval->entity_type,
            'entity_id' => $approval->entity_id,
            'work_order' => $workOrder === null ? null : [
                'id' => $workOrder->id,
                'work_order_number' => $workOrder->work_order_number,
                'title' => $workOrder->title,
                'asset_code' => $workOrder->asset?->asset_code,
            ],
            // The figure frozen when the request was raised, not today's
            // estimate — an estimate edited after approval would make "what
            // did they actually agree to" unanswerable (ERD Section 20).
            'context' => $approval->context_json,
            'status' => $approval->status,
            'current_step' => $approval->current_step,
            'total_steps' => $approval->total_steps,
            'requested_by' => $approval->requested_by,
            'requested_at' => $approval->requested_at?->toIso8601String(),
            'completed_at' => $approval->completed_at?->toIso8601String(),
            'expires_at' => $approval->expires_at?->toIso8601String(),
            'can_act' => $viewer !== null && $this->decisions->canAct($approval, $viewer),
        ];
    }

    /**
     * @param  Collection<int, \App\Modules\Approval\Models\ApprovalRule>|null  $rules
     * @return array<string, mixed>
     */
    private function detail(ApprovalRequest $approval, ?User $viewer = null, ?Collection $rules = null): array
    {
        return $this->summary($approval, $viewer) + [
            'actions' => $approval->relationLoaded('actions') ? $approval->actions->map(fn ($a): array => [
                'id' => $a->id,
                'approver_id' => $a->approver_id,
                'step' => $a->step,
                'action' => $a->action,
                'comment' => $a->comment,
                'acted_at' => $a->acted_at?->toIso8601String(),
            ])->all() : null,
            // The chain this particular request actually has to go through —
            // rules that didn't apply to the frozen context are already
            // excluded, same as `currentRule()` uses to decide who's next.
            'steps' => $rules?->values()->map(fn ($rule, int $index): array => [
                'step' => $index + 1,
                'name' => $rule->name ?? $rule->role?->description,
            ])->all(),
        ];
    }
}
