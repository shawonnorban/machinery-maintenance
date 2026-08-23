<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Http\Controllers\Api;

use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Breakdowns (API 12).
 *
 * Reporting one is the single most time-critical write in the product: a line
 * is stopped while somebody is filling in a form. So the endpoint asks for one
 * thing — which machine, and what is wrong — and lets the action supply
 * everything that can be derived. Priority comes from the machine's own
 * criticality unless the caller overrides it; the failure taxonomy can be
 * filled in later by whoever repairs it, and demanding it now would make the
 * fastest path through the system the slowest.
 *
 * Reporting is idempotent. A tablet on factory wifi that appears to hang gets
 * pressed again, and two breakdown numbers for one stoppage halve the MTBF of
 * a machine that broke once.
 */
class BreakdownApiController extends ApiController
{
    private const FILTERS = ['status', 'priority', 'severity', 'asset_id', 'factory_id'];

    private const SORTS = ['reported_at', 'failure_at', 'priority', 'status'];

    public function __construct(private readonly TenantContext $context) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow('breakdown.breakdown.view_any');

        $query = Breakdown::query()
            ->with(['asset:id,asset_code,name', 'factory:id,name'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if ($request->query('open') === 'true') {
            // The question a dashboard actually asks, answered by the model
            // rather than by a client hard-coding the list of open statuses.
            $query->whereIn('status', Breakdown::OPEN_STATUSES);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'reported_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Breakdown $breakdown): array => $this->summary($breakdown),
        );
    }

    public function show(Breakdown $breakdown): JsonResponse
    {
        $this->allow('breakdown.breakdown.view');
        $this->assertReachable($breakdown);

        $breakdown->load([
            'asset:id,asset_code,name', 'factory:id,name',
            'failureCategory:id,name', 'failureCode:id,name', 'rootCause:id,name',
            'assignedTechnician:id,name',
        ]);

        return ApiResponse::ok($this->detail($breakdown));
    }

    public function store(Request $request, ReportBreakdown $action): JsonResponse
    {
        $this->allow('breakdown.breakdown.create');

        $data = $request->validate([
            'asset_id' => ['required', 'string', 'size:26'],
            'problem_description' => ['required', 'string', 'max:5000'],
            'severity' => ['nullable', 'string', 'max:32'],
            'priority' => ['nullable', 'string', 'max:32'],
            'failure_at' => ['nullable', 'date'],
            'reported_at' => ['nullable', 'date'],
            'production_line_id' => ['nullable', 'string', 'size:26'],
            'failure_category_id' => ['nullable', 'string', 'size:26'],
            'failure_code_id' => ['nullable', 'string', 'size:26'],
            'production_order_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $breakdown = $action->handle($data, $this->caller()->auditUserId());

        return ApiResponse::created($this->detail($breakdown));
    }

    /**
     * Somebody has seen it (API 12).
     *
     * Acknowledgement is a named step rather than a status the client sets,
     * because it is what stops the escalation chain: a list being opened is
     * not the same as somebody taking responsibility for the machine.
     */
    public function acknowledge(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.acknowledge',
            fn (string $userId) => $action->acknowledge($breakdown, $userId),
        );
    }

    public function assign(Request $request, Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        $data = $request->validate([
            'technician_id' => ['required', 'string', 'size:26'],
        ]);

        return $this->step(
            $breakdown,
            'breakdown.breakdown.assign',
            fn (string $userId) => $action->assign($breakdown, $data['technician_id'], $userId),
        );
    }

    public function startRepair(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.repair',
            fn (string $userId) => $action->startRepair($breakdown, $userId),
        );
    }

    public function completeRepair(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.repair',
            fn (string $userId) => $action->completeRepair($breakdown, $userId),
        );
    }

    /**
     * The machine is making product again.
     *
     * Kept separate from "the repair is finished" because the two are minutes
     * to hours apart, and downtime is measured to this one. A repair that ends
     * at 3am on a line that restarts at 6 cost the factory three more hours.
     */
    public function resumeProduction(Breakdown $breakdown, TransitionBreakdown $action): JsonResponse
    {
        return $this->step(
            $breakdown,
            'breakdown.breakdown.repair',
            fn (string $userId) => $action->resumeProduction($breakdown, $userId),
        );
    }

    /**
     * Runs one transition and answers with the record it produced.
     *
     * A machine caller has no user id, and the actions want one: a step taken
     * by an integration is recorded against the integration, which is why
     * every one of these needs a caller that can say who it is.
     */
    private function step(Breakdown $breakdown, string $permission, callable $transition): JsonResponse
    {
        $this->allow($permission);
        $this->assertReachable($breakdown);

        $userId = $this->caller()->auditUserId();

        if ($userId === null) {
            throw ApiException::of(ErrorCode::FORBIDDEN, __('api.step_needs_a_person'));
        }

        return ApiResponse::ok($this->detail($transition($userId)));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Breakdown $breakdown): array
    {
        return [
            'id' => $breakdown->id,
            'breakdown_number' => $breakdown->breakdown_number,
            'status' => $breakdown->status,
            'priority' => $breakdown->priority,
            'severity' => $breakdown->severity,
            'asset' => [
                'id' => $breakdown->asset_id,
                'asset_code' => $breakdown->asset?->asset_code,
                'name' => $breakdown->asset?->name,
            ],
            'factory' => ['id' => $breakdown->factory_id, 'name' => $breakdown->factory?->name],
            'reported_at' => $breakdown->reported_at?->toIso8601String(),
            'failure_at' => $breakdown->failure_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Breakdown $breakdown): array
    {
        return $this->summary($breakdown) + [
            'problem_description' => $breakdown->problem_description,
            'failure_category' => $breakdown->failureCategory?->name,
            'failure_code' => $breakdown->failureCode?->name,
            'root_cause' => $breakdown->rootCause?->name,
            'corrective_action' => $breakdown->corrective_action,
            'preventive_action' => $breakdown->preventive_action,
            'assigned_technician' => $breakdown->assignedTechnician?->name,
            'production_order_reference' => $breakdown->production_order_reference,
            // A second report against a machine already down is the same event,
            // and the client is told which one it was folded into rather than
            // being left to wonder why its number looks familiar.
            'is_recurrence_of' => $breakdown->is_recurrence_of_breakdown_id,
            'is_open' => $breakdown->isOpen(),
        ];
    }

    private function assertReachable(Breakdown $breakdown): void
    {
        if (! $this->context->canAccessFactory((string) $breakdown->factory_id)) {
            abort(404);
        }
    }
}
