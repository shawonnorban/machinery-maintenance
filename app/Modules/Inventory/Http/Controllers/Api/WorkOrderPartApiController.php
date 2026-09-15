<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers\Api;

use App\Modules\Inventory\Actions\IssuePartsToWorkOrder;
use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\WorkOrderPart;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Services\WorkOrderCostCalculator;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Parts on a work order, over the wire (API 11; mirrors the web
 * `WorkOrderPartsController`, which this delegates to unchanged per
 * ADR-003 — `IssuePartsToWorkOrder` carries every issue/consume/return
 * reconciliation rule, and the cost recalculation that runs after each so
 * the work order's total can never drift from the lines under it,
 * ADR-064).
 *
 * Hosted in the Inventory module rather than WorkOrder, matching exactly
 * where the web controller and the models it moves (`WorkOrderPart`,
 * `SparePart`, `Bin`) already live.
 */
class WorkOrderPartApiController extends ApiController
{
    public function __construct(
        private readonly IssuePartsToWorkOrder $parts,
        private readonly WorkOrderCostCalculator $costs,
        private readonly TenantContext $context,
    ) {}

    public function index(WorkOrder $workOrder): JsonResponse
    {
        $this->allow('work_order.work_order.view');
        $this->assertReachable($workOrder);

        $lines = WorkOrderPart::where('work_order_id', $workOrder->id)
            ->with(['sparePart:id,part_number,name,unit', 'substituteFor:id,part_number'])
            ->orderBy('created_at')
            ->get();

        return ApiResponse::ok($lines->map(fn (WorkOrderPart $line): array => $this->summary($line))->all());
    }

    /**
     * What the floor is waiting for, across every open work order — mirrors
     * `PartRequestController::index()` exactly, including its own sort: a
     * stopped machine first, then the oldest wait, computed in PHP because
     * the priority lives on the work order and the wait on the request and
     * a join to sort on both would be harder to read than this is.
     */
    public function requests(): JsonResponse
    {
        $this->allow('inventory.part.view_any');

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
            ->sortBy([
                fn (WorkOrderPart $line) => array_search(
                    $line->workOrder?->priority,
                    ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'],
                    true,
                ) ?: 9,
                fn (WorkOrderPart $line) => $line->created_at?->getTimestamp() ?? 0,
            ])
            ->values();

        return ApiResponse::ok($lines->map(fn (WorkOrderPart $line): array => $this->requestSummary($line))->all());
    }

    public function store(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->allow('work_order.part.request');
        $this->assertReachable($workOrder);

        $data = $request->validate([
            'spare_part_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $line = $this->parts->request(
                $workOrder,
                SparePart::findOrFail($data['spare_part_id']),
                (string) $data['quantity'],
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        return ApiResponse::created($this->summary($line->load('sparePart:id,part_number,name,unit')));
    }

    /**
     * The only edit this line accepts: withdrawing a request that turned
     * out not to be needed, and only before anything has been issued
     * against it (`WorkOrderPartsController::cancelRequest`).
     */
    public function update(Request $request, WorkOrder $workOrder, string $line): JsonResponse
    {
        $this->allow('work_order.part.request');
        $this->assertReachable($workOrder);

        // Cancellation is the only edit this endpoint accepts, so the body
        // is validated for shape but nothing from it is otherwise used.
        $request->validate(['status' => ['required', 'in:CANCELLED']]);

        $record = $this->lineFor($workOrder, $line);

        if (bccomp((string) $record->quantity_issued, '0', 4) === 1) {
            throw ApiException::of(ErrorCode::CONFLICT, __('inventory.cannot_cancel_issued'));
        }

        $record->forceFill(['status' => 'CANCELLED'])->save();

        return ApiResponse::ok($this->summary($record->fresh(['sparePart:id,part_number,name,unit'])));
    }

    /**
     * The store handing a part over unprompted — no prior request line
     * exists yet (mirrors the web `WorkOrderPartsController::issue`,
     * which this delegates to unchanged per ADR-003; reservations are not
     * offered here, matching this pass's own scope). `IssuePartsToWorkOrder
     * ::issue()` already accepts a null `$line` and creates one internally,
     * so this is a thin wrapper rather than new business logic.
     */
    public function issueDirect(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $this->allow('inventory.stock.issue');
        $this->assertReachable($workOrder);

        $data = $request->validate([
            'spare_part_id' => ['required', 'string', 'size:26'],
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $line = $this->parts->issue(
                $workOrder,
                SparePart::findOrFail($data['spare_part_id']),
                Bin::findOrFail($data['bin_id']),
                (string) $data['quantity'],
                $this->caller()->auditUserId(),
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        $this->costs->recalculate($workOrder->fresh());

        return ApiResponse::created($this->summary($line->load('sparePart:id,part_number,name,unit')));
    }

    public function issue(Request $request, WorkOrder $workOrder, string $line): JsonResponse
    {
        $this->allow('inventory.stock.issue');
        $this->assertReachable($workOrder);

        $data = $request->validate([
            'bin_id' => ['required', 'string', 'size:26'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $record = $this->lineFor($workOrder, $line);

        try {
            $updated = $this->parts->issue(
                $workOrder,
                SparePart::findOrFail($record->spare_part_id),
                Bin::findOrFail($data['bin_id']),
                (string) $data['quantity'],
                $this->caller()->auditUserId(),
                line: $record,
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        $this->costs->recalculate($workOrder->fresh());

        return ApiResponse::ok($this->summary($updated->fresh(['sparePart:id,part_number,name,unit'])));
    }

    public function consume(Request $request, WorkOrder $workOrder, string $line): JsonResponse
    {
        $this->allow('inventory.stock.issue');
        $this->assertReachable($workOrder);

        $data = $request->validate(['quantity' => ['required', 'numeric', 'gt:0']]);

        try {
            $updated = $this->parts->consume(
                $this->lineFor($workOrder, $line),
                (string) $data['quantity'],
                $this->caller()->auditUserId(),
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        $this->costs->recalculate($workOrder->fresh());

        return ApiResponse::ok($this->summary($updated->fresh(['sparePart:id,part_number,name,unit'])));
    }

    public function returnToStore(Request $request, WorkOrder $workOrder, string $line): JsonResponse
    {
        $this->allow('inventory.stock.return');
        $this->assertReachable($workOrder);

        $data = $request->validate(['quantity' => ['required', 'numeric', 'gt:0']]);

        $record = $this->lineFor($workOrder, $line);

        if ($record->bin_id === null) {
            throw ApiException::of(ErrorCode::VALIDATION_ERROR, __('inventory.receive_needs_destination'));
        }

        try {
            $updated = $this->parts->returnToStore(
                $record,
                (string) $data['quantity'],
                $this->caller()->auditUserId(),
            );
        } catch (ValidationException $e) {
            throw $this->translate($e);
        }

        $this->costs->recalculate($workOrder->fresh());

        return ApiResponse::ok($this->summary($updated->fresh(['sparePart:id,part_number,name,unit'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestSummary(WorkOrderPart $line): array
    {
        $onHand = $line->sparePart?->totalOnHand() ?? '0';
        // The case the whole screen exists for: asked for, and not on the
        // shelf. Until this was visible the job just sat there.
        $short = bccomp($onHand, (string) $line->quantity_requested, 4) < 0;

        return [
            'id' => $line->id,
            'spare_part' => $line->relationLoaded('sparePart') && $line->sparePart !== null
                ? [
                    'id' => $line->sparePart->id,
                    'part_number' => $line->sparePart->part_number,
                    'name' => $line->sparePart->name,
                    'unit' => $line->sparePart->unit,
                ]
                : ['id' => $line->spare_part_id],
            'quantity_requested' => $line->quantity_requested,
            'on_hand' => $onHand,
            'short' => $short,
            'work_order' => $line->relationLoaded('workOrder') && $line->workOrder !== null
                ? [
                    'id' => $line->workOrder->id,
                    'work_order_number' => $line->workOrder->work_order_number,
                    'title' => $line->workOrder->title,
                    'priority' => $line->workOrder->priority,
                    'asset' => $line->workOrder->relationLoaded('asset') && $line->workOrder->asset !== null
                        ? ['id' => $line->workOrder->asset->id, 'asset_code' => $line->workOrder->asset->asset_code, 'name' => $line->workOrder->asset->name]
                        : null,
                ]
                : ['id' => $line->work_order_id],
            'created_at' => $line->created_at?->toIso8601String(),
        ];
    }

    private function lineFor(WorkOrder $workOrder, string $line): WorkOrderPart
    {
        return WorkOrderPart::where('work_order_id', $workOrder->id)
            ->where('id', $line)
            ->firstOrFail();
    }

    private function translate(ValidationException $e): ApiException
    {
        $status = $e->status ?? 422;
        $code = $status === 409 ? ErrorCode::CONFLICT : ErrorCode::VALIDATION_ERROR;

        return ApiException::of($code, implode(' ', $e->validator->errors()->all()), $e->errors());
    }

    private function assertReachable(WorkOrder $workOrder): void
    {
        if (! $this->context->canAccessFactory((string) $workOrder->factory_id)) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(WorkOrderPart $line): array
    {
        return [
            'id' => $line->id,
            'spare_part' => $line->relationLoaded('sparePart') && $line->sparePart !== null
                ? [
                    'id' => $line->sparePart->id,
                    'part_number' => $line->sparePart->part_number,
                    'name' => $line->sparePart->name,
                    'unit' => $line->sparePart->unit,
                ]
                : ['id' => $line->spare_part_id],
            'status' => $line->status,
            'quantity_requested' => $line->quantity_requested,
            'quantity_reserved' => $line->quantity_reserved,
            'quantity_issued' => $line->quantity_issued,
            'quantity_consumed' => $line->quantity_consumed,
            'quantity_returned' => $line->quantity_returned,
            'unit_cost' => $line->unit_cost,
            'total_cost' => $line->total_cost,
            'currency' => $line->currency,
        ];
    }
}
