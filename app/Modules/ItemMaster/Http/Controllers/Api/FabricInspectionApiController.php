<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Http\Controllers\Api;

use App\Modules\ItemMaster\Actions\ConductFabricInspection;
use App\Modules\ItemMaster\Models\FabricDefect;
use App\Modules\ItemMaster\Models\FabricInspection;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Modules\ItemMaster\Models\FabricRollDefect;
use App\Modules\ItemMaster\Services\FourPointScore;
use App\Modules\Procurement\Models\GoodsReceipt;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Four-point fabric inspection over the API (Inventory spec §4.3).
 *
 * The sheet is the lot, and rolls are tallied onto it — which is why
 * recording a roll posts to the *inspection*, not to the roll's own
 * endpoint. `FabricRollApiController::inspect()` stays where it is: it is
 * the one-roll shortcut for deliveries nobody four-points.
 */
class FabricInspectionApiController extends ApiController
{
    public const VIEW = 'itemmaster.roll.view_any';

    public const INSPECT = 'itemmaster.roll.inspect';

    public const APPROVE = 'itemmaster.inspection.approve';

    public function __construct(
        private readonly TenantContext $context,
        private readonly ConductFabricInspection $inspections,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = FabricInspection::query()
            ->with(['item:id,item_code,name', 'vendor:id,name', 'factory:id,name', 'goodsReceipt:id,grn_no'])
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(inspection_no) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(style_reference) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(buyer_reference) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(lot_no) LIKE ?', [$needle]));
        }

        $query = $this->applyFilters($query, $request, ['status', 'result', 'stage', 'factory_id', 'item_id']);
        $query = $this->applySort($query, $request, ['inspection_no', 'inspection_date', 'penalty_percent'], 'inspection_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (FabricInspection $inspection): array => $this->summary($inspection),
        );
    }

    public function formOptions(Request $request): JsonResponse
    {
        $this->allow(self::INSPECT);

        $family = $request->query('family');
        $defects = FabricDefect::query()
            ->availableTo((string) $this->context->companyId())
            ->where('active', true)
            ->when(in_array($family, FabricDefect::FAMILIES, true), fn ($q) => $q->where('fabric_family', $family))
            ->orderBy('name')
            ->get(['id', 'fabric_family', 'code', 'name']);

        return ApiResponse::ok([
            'defects' => $defects->all(),
            'point_classes' => array_keys(FourPointScore::POINT_CLASSES),
            'stages' => FabricInspection::STAGES,
            // The grid the mill's sheet prints: three lamps against three
            // comparisons.
            'light_sources' => ['D65', 'TL83', 'A'],
            'shade_aspects' => ['APPROVED', 'END_TO_END', 'SIDE_TO_CENTER'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::INSPECT);

        $data = $request->validate([
            'goods_receipt_id' => ['required', 'string', 'size:26'],
            'item_id' => ['required', 'string', 'size:26'],
            'stage' => ['nullable', Rule::in(FabricInspection::STAGES)],
            'buyer_reference' => ['nullable', 'string', 'max:120'],
            'style_reference' => ['nullable', 'string', 'max:120'],
            'order_reference' => ['nullable', 'string', 'max:120'],
            'colour' => ['nullable', 'string', 'max:64'],
            'composition' => ['nullable', 'string', 'max:120'],
            'lot_no' => ['nullable', 'string', 'max:64'],
            'rolls_in_lot' => ['nullable', 'integer', 'min:0'],
            'yards_in_lot' => ['nullable', 'numeric', 'gte:0'],
            'inspection_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $receipt = GoodsReceipt::findOrFail($data['goods_receipt_id']);

        if (! $this->context->canAccessFactory((string) $receipt->factory_id)) {
            abort(403);
        }

        return ApiResponse::created($this->detail($this->inspections->open(
            $receipt, $data['item_id'], $data, $this->caller()->auditUserId(),
        )));
    }

    public function show(FabricInspection $inspection): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($inspection);

        return ApiResponse::ok($this->detail($inspection));
    }

    public function recordRoll(Request $request, FabricInspection $inspection): JsonResponse
    {
        $this->allow(self::INSPECT);
        $this->assertReachable($inspection);

        $data = $request->validate([
            'fabric_roll_id' => ['required', 'string', 'size:26'],
            'width_start' => ['nullable', 'numeric', 'gt:0'],
            'width_middle' => ['nullable', 'numeric', 'gt:0'],
            'width_end' => ['nullable', 'numeric', 'gt:0'],
            'marked_length' => ['nullable', 'numeric', 'gte:0'],
            'inspected_length' => ['nullable', 'numeric', 'gt:0'],
            'shade_assessment' => ['nullable', 'array'],
            'defects' => ['nullable', 'array'],
            'defects.*.defect_id' => ['required', 'string', 'size:26'],
            'defects.*.point_class' => ['required', 'integer', 'between:1,4'],
            'defects.*.occurrences' => ['required', 'integer', 'min:1'],
            'defects.*.notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $roll = FabricRoll::findOrFail($data['fabric_roll_id']);

        $this->inspections->recordRoll($inspection, $roll, $data);

        return ApiResponse::ok($this->detail($inspection->refresh()));
    }

    public function submit(FabricInspection $inspection): JsonResponse
    {
        $this->allow(self::INSPECT);
        $this->assertReachable($inspection);

        return ApiResponse::ok($this->detail(
            $this->inspections->submit($inspection, $this->caller()->auditUserId()),
        ));
    }

    public function approve(FabricInspection $inspection): JsonResponse
    {
        $this->allow(self::APPROVE);
        $this->assertReachable($inspection);

        return ApiResponse::ok($this->detail(
            $this->inspections->approve($inspection, $this->caller()->auditUserId()),
        ));
    }

    public function reject(Request $request, FabricInspection $inspection): JsonResponse
    {
        $this->allow(self::APPROVE);
        $this->assertReachable($inspection);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        return ApiResponse::ok($this->detail(
            $this->inspections->reject($inspection, $data['reason'] ?? null, $this->caller()->auditUserId()),
        ));
    }

    public function escalate(FabricInspection $inspection): JsonResponse
    {
        $this->allow(self::INSPECT);
        $this->assertReachable($inspection);

        return ApiResponse::created($this->detail(
            $this->inspections->escalate($inspection, $this->caller()->auditUserId()),
        ));
    }

    public function cancel(Request $request, FabricInspection $inspection): JsonResponse
    {
        $this->allow(self::INSPECT);
        $this->assertReachable($inspection);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        return ApiResponse::ok($this->detail(
            $this->inspections->cancel($inspection, $data['reason'] ?? null),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(FabricInspection $inspection): array
    {
        return [
            'id' => $inspection->id,
            'inspection_no' => $inspection->inspection_no,
            'inspection_date' => $inspection->inspection_date?->toDateString(),
            'stage' => $inspection->stage,
            'sampling_percent' => (string) $inspection->sampling_percent,
            'status' => $inspection->status,
            'result' => $inspection->result,
            'rolls_in_lot' => $inspection->rolls_in_lot,
            'yards_in_lot' => (string) $inspection->yards_in_lot,
            'rolls_inspected' => $inspection->rolls_inspected,
            'yards_inspected' => (string) $inspection->yards_inspected,
            'total_points' => (string) $inspection->total_points,
            'penalty_percent' => (string) $inspection->penalty_percent,
            'acceptance_threshold' => (string) $inspection->acceptance_threshold,
            'buyer_reference' => $inspection->buyer_reference,
            'style_reference' => $inspection->style_reference,
            'order_reference' => $inspection->order_reference,
            'colour' => $inspection->colour,
            'lot_no' => $inspection->lot_no,
            'item' => $inspection->item === null ? null : [
                'id' => $inspection->item->id,
                'item_code' => $inspection->item->item_code,
                'name' => $inspection->item->name,
            ],
            'vendor' => $inspection->vendor === null ? null : ['id' => $inspection->vendor->id, 'name' => $inspection->vendor->name],
            'factory' => $inspection->factory === null ? null : ['id' => $inspection->factory->id, 'name' => $inspection->factory->name],
            'goods_receipt' => $inspection->goodsReceipt === null ? null : [
                'id' => $inspection->goodsReceipt->id,
                'grn_no' => $inspection->goodsReceipt->grn_no,
            ],
            'created_at' => $inspection->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(FabricInspection $inspection): array
    {
        $inspection->loadMissing([
            'item:id,item_code,name', 'vendor:id,name', 'factory:id,name',
            'goodsReceipt:id,grn_no', 'inspector:id,name', 'verifier:id,name', 'approver:id,name',
            'supersedes:id,inspection_no,stage',
        ]);

        $mayInspect = $this->caller()->can(self::INSPECT);
        $mayApprove = $this->caller()->can(self::APPROVE);

        // Every roll delivered on this receipt for this item, tallied or
        // not: the sheet has to show what is still waiting to be opened,
        // or a sample looks complete when half of it was never touched.
        $rolls = FabricRoll::where('goods_receipt_id', $inspection->goods_receipt_id)
            ->where('item_id', $inspection->item_id)
            ->orderBy('roll_no')
            ->get();

        $tally = FabricRollDefect::whereIn('fabric_roll_id', $rolls->pluck('id'))
            ->with('defect:id,code,name')
            ->get()
            ->groupBy('fabric_roll_id');

        return $this->summary($inspection) + [
            'composition' => $inspection->composition,
            'remarks' => $inspection->remarks,
            'supersedes' => $inspection->supersedes === null ? null : [
                'id' => $inspection->supersedes->id,
                'inspection_no' => $inspection->supersedes->inspection_no,
                'stage' => $inspection->supersedes->stage,
            ],
            'inspected_by' => $inspection->inspector?->name,
            'inspected_at' => $inspection->inspected_at?->toIso8601String(),
            'verified_by' => $inspection->verifier?->name,
            'verified_at' => $inspection->verified_at?->toIso8601String(),
            'approved_by' => $inspection->approver?->name,
            'approved_at' => $inspection->approved_at?->toIso8601String(),
            'can_record' => $inspection->isOpen() && $mayInspect,
            'can_submit' => $inspection->isOpen() && $mayInspect,
            'can_approve' => $inspection->canTransitionTo('APPROVED') && $mayApprove,
            'can_reject' => $inspection->canTransitionTo('REJECTED') && $mayApprove,
            'can_escalate' => $inspection->status === 'REJECTED'
                && $inspection->nextStage() !== null && $mayInspect,
            'can_cancel' => $inspection->canTransitionTo('CANCELLED') && $mayInspect,
            'next_stage' => $inspection->nextStage(),
            'rolls' => $rolls->map(fn (FabricRoll $roll): array => [
                'id' => $roll->id,
                'roll_no' => $roll->roll_no,
                'lot_no' => $roll->lot_no,
                'shade' => $roll->shade,
                'status' => $roll->status,
                'nominal_width' => $roll->nominal_width === null ? null : (string) $roll->nominal_width,
                'width_start' => $roll->width_start === null ? null : (string) $roll->width_start,
                'width_middle' => $roll->width_middle === null ? null : (string) $roll->width_middle,
                'width_end' => $roll->width_end === null ? null : (string) $roll->width_end,
                'actual_width' => $roll->actual_width === null ? null : (string) $roll->actual_width,
                'marked_length' => $roll->marked_length === null ? null : (string) $roll->marked_length,
                'inspected_length' => $roll->inspected_length === null ? null : (string) $roll->inspected_length,
                'length_shortfall' => $roll->lengthShortfall(),
                'net_length' => $roll->net_length === null ? null : (string) $roll->net_length,
                'inspection_points' => $roll->inspection_points === null ? null : (string) $roll->inspection_points,
                'penalty_percent' => $roll->penalty_percent === null ? null : (string) $roll->penalty_percent,
                'inspection_grade' => $roll->inspection_grade,
                'shade_assessment' => $roll->shade_assessment,
                'shade_clean' => $roll->shadeIsClean(),
                // Tallied onto this sheet, as opposed to still waiting.
                'recorded' => (string) $roll->fabric_inspection_id === (string) $inspection->id
                    && $roll->inspected_length !== null,
                'defects' => ($tally->get($roll->id) ?? collect())
                    ->map(fn (FabricRollDefect $row): array => [
                        'defect_id' => $row->fabric_defect_id,
                        'code' => $row->defect?->code,
                        'name' => $row->defect?->name,
                        'point_class' => $row->point_class,
                        'occurrences' => $row->occurrences,
                        'points' => $row->points(),
                    ])->values()->all(),
            ])->all(),
        ];
    }

    private function assertReachable(FabricInspection $inspection): void
    {
        if (! $this->context->canAccessFactory((string) $inspection->factory_id)) {
            abort(404);
        }
    }
}
