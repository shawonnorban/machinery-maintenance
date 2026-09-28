<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Http\Controllers\Api;

use App\Modules\Procurement\Actions\RaiseRequirementRequisition;
use App\Modules\Procurement\Services\MaterialRequirement;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

/**
 * What still has to be bought for a cutting programme or a style
 * (Procurement spec §4), and the draft requisition raised from it (§5.3).
 *
 * Read-only by design apart from that one POST: the requirement is a
 * calculation, never a stored document, so there is nothing else to edit.
 */
class MaterialRequirementApiController extends ApiController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MaterialRequirement $requirement,
        private readonly RaiseRequirementRequisition $autoRequisition,
    ) {}

    public function forProgram(CuttingProgram $program): JsonResponse
    {
        $this->allow('production.order.view_any');
        $this->assertReachable((string) $program->factory_id);

        $lines = $this->requirement->forProgram($program);

        return ApiResponse::ok([
            'cutting_program' => [
                'id' => $program->id,
                'program_no' => $program->program_no,
                'program_qty' => (string) $program->program_qty,
                'cutting_date' => $program->cutting_date?->toDateString(),
            ],
            'lines' => $lines,
            'has_shortfall' => MaterialRequirement::hasShortfall($lines),
            'can_raise_requisition' => MaterialRequirement::hasShortfall($lines)
                && $this->caller()->can(PurchaseRequisitionApiController::CREATE),
        ]);
    }

    public function forStyle(Style $style): JsonResponse
    {
        $this->allow('production.order.view_any');

        $order = Order::findOrFail($style->production_order_id);
        $this->assertReachable((string) $order->factory_id);

        $lines = $this->requirement->forStyle($style, (string) $order->factory_id);

        return ApiResponse::ok([
            'style' => ['id' => $style->id, 'style_no' => $style->style_no, 'quantity' => (string) $style->quantity],
            'lines' => $lines,
            'has_shortfall' => MaterialRequirement::hasShortfall($lines),
        ]);
    }

    /** The automatic draft requisition for whatever the programme is short. */
    public function raiseRequisition(CuttingProgram $program): JsonResponse
    {
        $this->allow(PurchaseRequisitionApiController::CREATE);
        $this->assertReachable((string) $program->factory_id);

        $requisition = $this->autoRequisition->forProgram($program, $this->caller()->auditUserId());

        return ApiResponse::created([
            'id' => $requisition->id,
            'pr_no' => $requisition->pr_no,
            'status' => $requisition->status,
            'line_count' => $requisition->lines()->count(),
        ]);
    }

    private function assertReachable(string $factoryId): void
    {
        if (! $this->context->canAccessFactory($factoryId)) {
            abort(404);
        }
    }
}
