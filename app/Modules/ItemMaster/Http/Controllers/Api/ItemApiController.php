<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Http\Controllers\Api;

use App\Modules\Asset\Models\Manufacturer;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\ItemMaster\Actions\DeleteItem;
use App\Modules\ItemMaster\Actions\SaveItem;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\ItemMaster\Models\ItemFabricAttributes;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\ItemMaster\Models\ItemStockBalance;
use App\Modules\ItemMaster\Services\LotSuggestion;
use App\Modules\Procurement\Models\HsCode;
use App\Modules\Vendor\Models\Vendor;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The item library over the wire (Master Data spec §2, §9).
 *
 * Two audiences: anyone who raises a requisition reads it (department →
 * item, with search, because a general store has five thousand rows), and
 * the admins who maintain it write to it. The library is the only place an
 * item comes from — a transaction never invents one.
 */
class ItemApiController extends ApiController
{
    public const VIEW = 'itemmaster.item.view_any';

    public const MANAGE = 'itemmaster.item.manage';

    private const FILTERS = [
        'item_department_id', 'category_id', 'sub_category_id', 'detail_category_id',
        'item_type', 'tracking_mode', 'active',
    ];

    private const SORTS = ['item_code', 'name', 'created_at'];

    /** Everything `show()` needs to render an item, spec included. */
    private const DETAIL_RELATIONS = [
        'itemDepartment:id,name', 'category:id,name', 'subCategory:id,name', 'detailCategory:id,name',
        'uom:id,code,name', 'purchaseUom:id,code,name', 'manufacturer:id,name', 'defaultStore:id,name',
        'hsCode:id,code,description', 'defaultVendor:id,name', 'fabricAttributes', 'yarnAttributes', 'chemicalAttributes',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly LotSuggestion $lots,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = Item::query()
            ->with(['itemDepartment:id,name', 'category:id,name', 'subCategory:id,name', 'detailCategory:id,name', 'uom:id,code,name'])
            ->withSum('balances as on_hand', 'quantity_on_hand');

        if (is_string($search = $request->query('search')) && $search !== '') {
            // LOWER() on both sides: Postgres LIKE is case-sensitive, and a
            // storekeeper typing "combed cotton" means "Combed Cotton".
            $term = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(item_code) LIKE ?', [$term])
                ->orWhereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(brand) LIKE ?', [$term]));
        }

        if ($request->boolean('below_reorder')) {
            $this->onlyBelowReorder($query);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'name', 'asc');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Item $item): array => $this->summary($item),
        );
    }

    /**
     * Items at or below their reorder level, emptiest first (Inventory spec
     * §16, Phase 1 reorder alert). The same list the low-stock notification
     * is raised from, so what the store was told and what it sees agree.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = Item::query()
            ->with(['itemDepartment:id,name', 'uom:id,code,name', 'category:id,name', 'subCategory:id,name', 'detailCategory:id,name'])
            ->withSum('balances as on_hand', 'quantity_on_hand')
            ->where('active', true);

        $this->onlyBelowReorder($query);
        $query = $this->applyFilters($query, $request, ['item_department_id', 'item_type']);

        return ApiResponse::paginated(
            $query->orderBy('on_hand')->orderBy('name')->paginate($this->perPage($request))->withQueryString(),
            fn (Item $item): array => $this->summary($item),
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Item>  $query
     */
    private function onlyBelowReorder($query): void
    {
        // A zero level means "nobody set one" and never alerts. ROLL-tracked
        // items are excluded: they never write to item_stock_balances (the
        // roll ledger is their stock), so without this every fabric item
        // with a reorder_level set would show as permanently below it.
        // Fabric replenishment is booking/requirement-driven (Phase 3), not
        // a simple reorder-level threshold.
        $query->where('reorder_level', '>', 0)
            ->where('tracking_mode', '!=', 'ROLL')
            ->whereRaw('COALESCE((SELECT SUM(b.quantity_on_hand) FROM item_stock_balances b WHERE b.item_id = items.id), 0) <= items.reorder_level');
    }

    public function show(Item $item): JsonResponse
    {
        $this->allow(self::VIEW);

        return ApiResponse::ok($this->detail($item->load(self::DETAIL_RELATIONS)));
    }

    /**
     * Every list the create/edit form picks from. Gated on the view
     * permission rather than `masterdata.manage`: the person who maintains
     * the library is not necessarily the one who maintains master data.
     */
    public function formOptions(): JsonResponse
    {
        $this->allow(self::VIEW);

        $companyId = $this->context->companyId();

        return ApiResponse::ok([
            'item_departments' => ItemDepartment::query()->where('active', true)
                ->orderBy('name')->get(['id', 'name', 'default_item_type'])->all(),
            // parent_id lets the form cascade category → sub-category → detail.
            'categories' => ItemCategory::query()->where('active', true)
                ->orderBy('name')->get(['id', 'name', 'parent_id'])->all(),
            'uoms' => UnitOfMeasure::availableTo($companyId)->where('active', true)
                ->orderBy('code')->get(['id', 'code', 'name'])->all(),
            'manufacturers' => Manufacturer::availableTo($companyId)->where('active', true)
                ->orderBy('name')->get(['id', 'name'])->all(),
            'stores' => Store::query()->where('active', true)->with('warehouse:id,name')
                ->orderBy('name')->get()
                ->map(fn (Store $store): array => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'warehouse' => $store->warehouse?->name,
                ])->all(),
            'hs_codes' => HsCode::availableTo($companyId)->where('active', true)
                ->orderBy('code')->get(['id', 'code', 'description'])->all(),
            'vendors' => Vendor::query()->where('status', 'ACTIVE')
                ->whereIn('vendor_type', ['SUPPLIER', 'BOTH'])
                ->orderBy('name')->get(['id', 'name'])->all(),
            'item_types' => Item::ITEM_TYPES,
            'tracking_modes' => Item::TRACKING_MODES,
            'fabric_types' => ItemFabricAttributes::FABRIC_TYPES,
        ]);
    }

    public function store(Request $request, SaveItem $action): JsonResponse
    {
        $this->allow(self::MANAGE);

        $item = $action->create($this->validated($request, null) + ['created_by' => $this->caller()->auditUserId()]);

        return ApiResponse::created($this->detail($item->load(self::DETAIL_RELATIONS)));
    }

    public function update(Request $request, Item $item, SaveItem $action): JsonResponse
    {
        $this->allow(self::MANAGE);

        $updated = $action->update($item, $this->validated($request, $item));

        return ApiResponse::ok($this->detail($updated->load(self::DETAIL_RELATIONS)));
    }

    public function setActive(Request $request, Item $item, SaveItem $action): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $request->validate(['active' => ['required', 'boolean']]);

        return ApiResponse::ok($this->detail($action->setActive($item, $data['active'])->load(self::DETAIL_RELATIONS)));
    }

    public function destroy(Item $item, DeleteItem $action): JsonResponse
    {
        $this->allow(self::MANAGE);

        try {
            $action->handle($item);
        } catch (ValidationException $e) {
            throw ApiException::of(ErrorCode::CONFLICT, implode(' ', $e->validator->errors()->all()));
        }

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Item $item): array
    {
        $named = fn (?object $row, ?string $id): ?array => $row === null ? null : ['id' => $id, 'name' => $row->name];

        return [
            'id' => $item->id,
            'item_code' => $item->item_code,
            'name' => $item->name,
            'item_type' => $item->item_type,
            'tracking_mode' => $item->tracking_mode,
            'description' => $item->description,
            'item_department' => $named($item->itemDepartment, $item->item_department_id),
            'category' => $named($item->category, $item->category_id),
            'sub_category' => $named($item->subCategory, $item->sub_category_id),
            'detail_category' => $named($item->detailCategory, $item->detail_category_id),
            'uom' => $item->uom === null ? null : ['id' => $item->uom_id, 'code' => $item->uom->code, 'name' => $item->uom->name],
            'model' => $item->model,
            'brand' => $item->brand,
            'origin' => $item->origin,
            'reorder_level' => $item->reorder_level,
            // Present on list rows (withSum); null when the row was loaded
            // without it.
            'on_hand' => array_key_exists('on_hand', $item->getAttributes())
                ? number_format((float) ($item->getAttribute('on_hand') ?? 0), 4, '.', '')
                : null,
            'active' => (bool) $item->active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Item $item): array
    {
        $named = fn (?object $row, ?string $id): ?array => $row === null ? null : ['id' => $id, 'name' => $row->name];

        return $this->summary($item) + [
            'legacy_item_id' => $item->legacy_item_id,
            'purchase_uom' => $item->purchaseUom === null ? null : [
                'id' => $item->purchase_uom_id, 'code' => $item->purchaseUom->code, 'name' => $item->purchaseUom->name,
            ],
            'manufacturer' => $named($item->manufacturer, $item->manufacturer_id),
            'default_store' => $named($item->defaultStore, $item->default_store_id),
            'hs_code' => $item->hsCode === null ? null : [
                'id' => $item->hs_code_id, 'code' => $item->hsCode->code, 'description' => $item->hsCode->description,
            ],
            'default_vendor' => $named($item->defaultVendor, $item->default_vendor_id),
            'minimum_stock' => $item->minimum_stock,
            'is_returnable' => (bool) $item->is_returnable,
            'hazardous' => (bool) $item->hazardous,
            'fabric' => $item->fabricAttributes?->only([
                'fabric_type', 'composition', 'gsm', 'width', 'construction', 'finish', 'shrinkage', 'pantone', 'color_fastness',
            ]),
            'yarn' => $item->yarnAttributes?->only(['count', 'type', 'fiber', 'twist', 'color']),
            'chemical' => $item->chemicalAttributes?->only(['expiry_tracking', 'hazard_info', 'batch_tracking']),
            // A ROLL-tracked item never writes to item_stock_balances (the
            // roll ledger is its stock — Inventory spec §3); its own
            // summary replaces the quantity block, which would always read
            // zero for it.
            'stock' => $item->usesRollLedger() ? null : $this->stock($item),
            'rolls' => $item->usesRollLedger() ? $item->fabricStockSummary() : null,
            // Why stock that is visibly on the shelf cannot be issued.
            'reservations' => $this->reservations($item),
            // Which lot to draw from, nearest expiry then FIFO (§12).
            'lots' => $item->isLotTracked() ? $this->lots->forItem($item) : null,
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }

    /**
     * What the item stock ledger holds for this item, per bin and lot.
     *
     * @return array<string, mixed>
     */
    private function stock(Item $item): array
    {
        $balances = ItemStockBalance::where('item_id', $item->id)
            ->with('bin:id,code,name,store_id', 'bin.store:id,name')
            ->orderBy('bin_id')->orderBy('lot_no')
            ->get();

        $onHand = $balances->reduce(fn (string $sum, ItemStockBalance $b): string => bcadd($sum, (string) $b->quantity_on_hand, 4), '0.0000');
        $reserved = $balances->reduce(fn (string $sum, ItemStockBalance $b): string => bcadd($sum, (string) $b->quantity_reserved, 4), '0.0000');
        $value = $balances->reduce(fn (string $sum, ItemStockBalance $b): string => bcadd($sum, $b->totalValue(), 4), '0.0000');

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => bcsub($onHand, $reserved, 4),
            'value' => $value,
            'below_reorder' => bccomp((string) ($item->reorder_level ?? '0'), '0', 4) > 0 && bccomp($onHand, (string) $item->reorder_level, 4) <= 0,
            'by_bin' => $balances
                ->filter(fn (ItemStockBalance $b): bool => bccomp((string) $b->quantity_on_hand, '0', 4) !== 0 || bccomp((string) $b->quantity_reserved, '0', 4) !== 0)
                ->map(fn (ItemStockBalance $b): array => [
                    'bin' => $b->bin === null ? null : ['id' => $b->bin->id, 'code' => $b->bin->code, 'name' => $b->bin->name, 'store' => $b->bin->store?->name],
                    'lot_no' => $b->lot_no === '' ? null : $b->lot_no,
                    'quantity_on_hand' => (string) $b->quantity_on_hand,
                    'quantity_reserved' => (string) $b->quantity_reserved,
                    'weighted_average_cost' => (string) $b->weighted_average_cost,
                    'currency' => $b->currency,
                ])->values()->all(),
        ];
    }

    /**
     * Everything still holding this item away from the next requisition,
     * including the claims on stock that has not arrived yet.
     *
     * @return list<array<string, mixed>>
     */
    private function reservations(Item $item): array
    {
        return ItemReservation::where('item_id', $item->id)
            ->whereIn('status', ItemReservation::OPEN_STATUSES)
            ->with(['bin:id,code,name', 'fabricRoll:id,roll_no', 'reserver:id,name'])
            ->orderBy('reserved_at')
            ->get()
            ->map(fn (ItemReservation $r): array => [
                'id' => $r->id,
                'status' => $r->status,
                'reservation_type' => $r->reservation_type,
                'reference_type' => $r->reference_type,
                'reference_id' => $r->reference_id,
                'quantity' => (string) $r->quantity,
                'outstanding' => $r->outstanding(),
                'bin' => $r->bin === null ? null : ['id' => $r->bin->id, 'code' => $r->bin->code, 'name' => $r->bin->name],
                'lot_no' => $r->lot_no === '' ? null : $r->lot_no,
                'roll_no' => $r->fabricRoll?->roll_no,
                'reserved_by' => $r->reserver?->name,
                'reserved_at' => $r->reserved_at?->toIso8601String(),
                'expires_at' => $r->expires_at?->toIso8601String(),
            ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Item $item): array
    {
        $unique = Rule::unique('items', 'item_code')->where('company_id', $this->context->companyId());

        if ($item !== null) {
            $unique = $unique->ignore($item->id);
        }

        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:64', $unique],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'item_department_id' => ['nullable', 'string', 'size:26'],
            'category_id' => ['nullable', 'string', 'size:26'],
            'sub_category_id' => ['nullable', 'string', 'size:26'],
            'detail_category_id' => ['nullable', 'string', 'size:26'],
            // Absent (not null) on create lets the department's default decide.
            'item_type' => ['sometimes', 'nullable', Rule::in(Item::ITEM_TYPES)],
            'tracking_mode' => ['sometimes', 'nullable', Rule::in(Item::TRACKING_MODES)],
            'uom_id' => [$item === null ? 'required' : 'sometimes', 'string', 'size:26'],
            'purchase_uom_id' => ['nullable', 'string', 'size:26'],
            'model' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:255'],
            'manufacturer_id' => ['nullable', 'string', 'size:26'],
            'origin' => ['nullable', 'string', 'max:100'],
            'default_store_id' => ['nullable', 'string', 'size:26'],
            'hs_code_id' => ['nullable', 'string', 'size:26'],
            'default_vendor_id' => ['nullable', 'string', 'size:26'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'is_returnable' => ['sometimes', 'boolean'],
            'hazardous' => ['sometimes', 'boolean'],

            'fabric' => ['sometimes', 'array'],
            'fabric.fabric_type' => ['nullable', Rule::in(ItemFabricAttributes::FABRIC_TYPES)],
            'fabric.composition' => ['nullable', 'string', 'max:255'],
            'fabric.gsm' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'fabric.width' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'fabric.construction' => ['nullable', 'string', 'max:255'],
            'fabric.finish' => ['nullable', 'string', 'max:255'],
            'fabric.shrinkage' => ['nullable', 'string', 'max:64'],
            'fabric.pantone' => ['nullable', 'string', 'max:32'],
            'fabric.color_fastness' => ['nullable', 'string', 'max:64'],

            'yarn' => ['sometimes', 'array'],
            'yarn.count' => ['nullable', 'string', 'max:32'],
            'yarn.type' => ['nullable', 'string', 'max:64'],
            'yarn.fiber' => ['nullable', 'string', 'max:255'],
            'yarn.twist' => ['nullable', 'string', 'max:32'],
            'yarn.color' => ['nullable', 'string', 'max:64'],

            'chemical' => ['sometimes', 'array'],
            'chemical.expiry_tracking' => ['nullable', 'boolean'],
            'chemical.hazard_info' => ['nullable', 'string', 'max:2000'],
            'chemical.batch_tracking' => ['nullable', 'boolean'],
        ]);

        // An empty select posts "" — that means "not chosen", never "the
        // literal empty string".
        foreach (['item_type', 'tracking_mode'] as $enum) {
            if (array_key_exists($enum, $validated) && ! filled($validated[$enum])) {
                unset($validated[$enum]);
            }
        }

        foreach (['is_returnable', 'hazardous'] as $flag) {
            if ($request->has($flag)) {
                $validated[$flag] = $request->boolean($flag);
            }
        }

        if (isset($validated['chemical'])) {
            $validated['chemical']['expiry_tracking'] = $request->boolean('chemical.expiry_tracking');
            $validated['chemical']['batch_tracking'] = $request->boolean('chemical.batch_tracking');
        }

        return $validated;
    }
}
