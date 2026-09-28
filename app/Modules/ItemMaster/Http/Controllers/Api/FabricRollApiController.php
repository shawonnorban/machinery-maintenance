<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Http\Controllers\Api;

use App\Modules\ItemMaster\Actions\InspectFabricRoll;
use App\Modules\ItemMaster\Actions\MergeFabricRolls;
use App\Modules\ItemMaster\Actions\SplitFabricRoll;
use App\Modules\ItemMaster\Models\FabricDefect;
use App\Modules\ItemMaster\Models\FabricRoll;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fabric roll inspection over the wire (Inventory spec §3, §4) — single
 * ownership, as the spec's §4.1 ownership table calls for: this is the one
 * screen a roll's status ever changes from DRAFT/UNDER_INSPECTION on.
 */
class FabricRollApiController extends ApiController
{
    public const VIEW = 'itemmaster.roll.view_any';

    public const INSPECT = 'itemmaster.roll.inspect';

    /** Cutting and joining rolls (spec §5, §6) — the same store-floor hands. */
    public const SPLIT = 'itemmaster.roll.split';

    private const FILTERS = ['status', 'item_id', 'goods_receipt_id'];

    private const SORTS = ['roll_no', 'received_date', 'status', 'created_at'];

    private const RELATIONS = [
        'item:id,item_code,name,tracking_mode', 'item.fabricAttributes',
        'goodsReceipt:id,grn_no,status', 'bin:id,code,name,store_id', 'inspector:id,name', 'defects',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly InspectFabricRoll $inspections,
        private readonly SplitFabricRoll $splits,
        private readonly MergeFabricRolls $merges,
    ) {}

    public function formOptions(): JsonResponse
    {
        $this->allow(self::VIEW);

        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'defects' => FabricDefect::availableTo($companyId)->where('active', true)->orderBy('name')
                ->get(['id', 'fabric_family', 'code', 'name'])
                ->groupBy('fabric_family'),
            'grades' => FabricRoll::GRADES,
            'dispositions' => FabricRoll::DISPOSITIONS,
            'statuses' => FabricRoll::STATUSES,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = FabricRoll::query()
            ->with(['item:id,item_code,name', 'goodsReceipt:id,grn_no', 'bin:id,code,name'])
            ->when(
                is_string($storeId = $request->query('store_id')) && $storeId !== '',
                fn ($q) => $q->whereHas('bin', fn ($b) => $b->where('store_id', $storeId)),
            );

        if (! $request->has('status')) {
            // The screen exists to work the queue; default to what needs doing.
            $query->where('status', 'UNDER_INSPECTION');
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'received_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (FabricRoll $roll): array => $this->summary($roll),
        );
    }

    public function show(FabricRoll $roll): JsonResponse
    {
        $this->allow(self::VIEW);

        return ApiResponse::ok($this->detail($roll));
    }

    /**
     * Cutting one roll into several (spec §5). The children must add up to
     * the parent; the action refuses anything else.
     */
    public function split(Request $request, FabricRoll $roll): JsonResponse
    {
        $this->allow(self::SPLIT);

        $data = $request->validate([
            'children' => ['required', 'array', 'min:2'],
            'children.*.roll_no' => ['required', 'string', 'max:64'],
            'children.*.net_length' => ['nullable', 'numeric', 'gt:0'],
            'children.*.net_weight' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $children = $this->splits->handle($roll, $data['children'], $this->caller()->auditUserId());

        return ApiResponse::created([
            'parent' => $this->detail($roll->fresh()),
            'children' => $children->map(fn (FabricRoll $child): array => $this->summary($child))->all(),
        ]);
    }

    /** Joining several rolls into one (spec §6). */
    public function merge(Request $request): JsonResponse
    {
        $this->allow(self::SPLIT);

        $data = $request->validate([
            'roll_ids' => ['required', 'array', 'min:2'],
            'roll_ids.*' => ['string', 'size:26'],
            'roll_no' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::created($this->detail(
            $this->merges->handle($data['roll_ids'], $data, $this->caller()->auditUserId()),
        ));
    }

    public function inspect(Request $request, FabricRoll $roll): JsonResponse
    {
        $this->allow(self::INSPECT);

        $data = $request->validate([
            'actual_width' => ['nullable', 'numeric', 'min:0'],
            'shade_match' => ['sometimes', 'boolean'],
            'grade' => ['required', Rule::in(FabricRoll::GRADES)],
            'disposition' => ['nullable', Rule::in(FabricRoll::DISPOSITIONS)],
            'defect_ids' => ['nullable', 'array'],
            'defect_ids.*' => ['string', 'size:26'],
            'inspection_points' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::ok($this->detail(
            $this->inspections->handle($roll, $data, $this->caller()->auditUserId()),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(FabricRoll $roll): array
    {
        return [
            'id' => $roll->id,
            'roll_no' => $roll->roll_no,
            'status' => $roll->status,
            'item' => $roll->item === null ? null : ['id' => $roll->item->id, 'item_code' => $roll->item->item_code, 'name' => $roll->item->name],
            'goods_receipt' => $roll->goodsReceipt === null ? null : ['id' => $roll->goodsReceipt->id, 'grn_no' => $roll->goodsReceipt->grn_no],
            'bin' => $roll->bin === null ? null : ['id' => $roll->bin->id, 'code' => $roll->bin->code, 'name' => $roll->bin->name],
            'shade' => $roll->shade,
            'nominal_width' => $roll->nominal_width === null ? null : (string) $roll->nominal_width,
            'net_length' => $roll->net_length === null ? null : (string) $roll->net_length,
            'net_weight' => $roll->net_weight === null ? null : (string) $roll->net_weight,
            'received_date' => $roll->received_date?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(FabricRoll $roll): array
    {
        $roll->loadMissing(self::RELATIONS);

        return $this->summary($roll) + [
            'lot_no' => $roll->lot_no,
            'shade_band' => $roll->shade_band,
            'actual_width' => $roll->actual_width === null ? null : (string) $roll->actual_width,
            'gross_length' => $roll->gross_length === null ? null : (string) $roll->gross_length,
            'gross_weight' => $roll->gross_weight === null ? null : (string) $roll->gross_weight,
            'unit_cost' => (string) $roll->unit_cost,
            'currency' => $roll->currency,
            'defect_family' => $roll->defectFamily(),
            'shade_match' => $roll->shade_match,
            'inspection_grade' => $roll->inspection_grade,
            'inspection_points' => $roll->inspection_points === null ? null : (string) $roll->inspection_points,
            'inspection_notes' => $roll->inspection_notes,
            'inspected_by' => $roll->inspector === null ? null : ['id' => $roll->inspector->id, 'name' => $roll->inspector->name],
            'inspected_at' => $roll->inspected_at?->toIso8601String(),
            'defects' => $roll->defects->map(fn (FabricDefect $d): array => ['id' => $d->id, 'code' => $d->code, 'name' => $d->name])->all(),
            'parent_roll_id' => $roll->parent_roll_id,
            'merged_into_roll_id' => $roll->merged_into_roll_id,
            // §10.5: the price changed after the cloth left the store.
            'cost_adjusted_after_issue' => (bool) $roll->cost_adjusted_after_issue,
            'can_inspect' => $roll->isPendingInspection() && $this->caller()->can(self::INSPECT),
            'can_split' => $roll->isOnShelf() && $this->caller()->can(self::SPLIT),
            'can_merge' => $roll->status === 'AVAILABLE' && $this->caller()->can(self::SPLIT),
        ];
    }
}
