<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers\Api;

use App\Modules\Production\Actions\IssueToProduction;
use App\Modules\Production\Models\CuttingProgram;
use App\Modules\Production\Models\MaterialIssue;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issuing material to a cut and reading the variance it produced
 * (Inventory spec §9).
 *
 * An issue past the factory's tolerance comes back as PENDING_APPROVAL with
 * no stock moved — the response says so plainly rather than looking like a
 * success, because the storekeeper is standing at the counter deciding
 * whether to hand the fabric over.
 */
class MaterialIssueApiController extends ApiController
{
    public const ISSUE = 'production.issue.create';

    public function __construct(
        private readonly TenantContext $context,
        private readonly IssueToProduction $issues,
    ) {}

    public function index(CuttingProgram $program): JsonResponse
    {
        $this->allow(ProductionOrderApiController::VIEW);
        $this->assertReachable($program);

        $issues = MaterialIssue::where('cutting_program_id', $program->id)
            ->with(['bin:id,code,name', 'fabricRoll:id,roll_no', 'uom:id,code,name', 'issuer:id,name'])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();

        return ApiResponse::ok([
            'cutting_program' => [
                'id' => $program->id,
                'program_no' => $program->program_no,
                'program_qty' => (string) $program->program_qty,
                'status' => $program->status,
            ],
            'variance' => $this->issues->variance($program),
            'issues' => $issues->map(fn (MaterialIssue $issue): array => $this->summary($issue))->all(),
            'can_issue' => $program->isOpen() && $this->caller()->can(self::ISSUE),
        ]);
    }

    public function store(Request $request, CuttingProgram $program): JsonResponse
    {
        $this->allow(self::ISSUE);
        $this->assertReachable($program);

        $data = $request->validate([
            'item_id' => ['required', 'string', 'size:26'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'bin_id' => ['nullable', 'string', 'size:26'],
            'lot_no' => ['nullable', 'string', 'max:64'],
            'fabric_roll_id' => ['nullable', 'string', 'size:26'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $issue = $this->issues->handle($program, $data, $this->caller()->auditUserId());

        return ApiResponse::created($this->summary($issue) + [
            'variance' => $this->issues->variance($program->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(MaterialIssue $issue): array
    {
        $issue->loadMissing(['bin:id,code,name', 'fabricRoll:id,roll_no', 'uom:id,code,name', 'issuer:id,name']);

        return [
            'id' => $issue->id,
            'item_id' => $issue->item_id,
            'item_code' => $issue->item_code,
            'item_name' => $issue->item_name,
            'uom' => $issue->uom === null ? null : ['code' => $issue->uom->code, 'name' => $issue->uom->name],
            'bin' => $issue->bin === null ? null : ['id' => $issue->bin->id, 'code' => $issue->bin->code, 'name' => $issue->bin->name],
            'lot_no' => $issue->lot_no,
            'roll_no' => $issue->fabricRoll?->roll_no,
            'quantity' => (string) $issue->quantity,
            'unit_cost' => (string) $issue->unit_cost,
            'currency' => $issue->currency,
            'required_qty' => (string) $issue->required_qty,
            'issued_to_date' => (string) $issue->issued_to_date,
            'variance_qty' => (string) $issue->variance_qty,
            'variance_percent' => (string) $issue->variance_percent,
            'is_extra' => (bool) $issue->is_extra,
            'reason' => $issue->reason,
            'status' => $issue->status,
            'approval_status' => $issue->approval_status,
            'approval_request_id' => $issue->approval_request_id,
            // The one thing the counter needs to know: did the stock move?
            'stock_moved' => $issue->item_stock_transaction_id !== null || $issue->status === 'POSTED',
            'issued_by' => $issue->issuer === null ? null : ['id' => $issue->issuer->id, 'name' => $issue->issuer->name],
            'issued_at' => $issue->issued_at?->toIso8601String(),
        ];
    }

    private function assertReachable(CuttingProgram $program): void
    {
        if (! $this->context->canAccessFactory((string) $program->factory_id)) {
            abort(404);
        }
    }
}
