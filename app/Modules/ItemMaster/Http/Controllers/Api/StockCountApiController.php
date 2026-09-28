<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Http\Controllers\Api;

use App\Modules\Inventory\Models\Bin;
use App\Modules\Inventory\Models\Store;
use App\Modules\ItemMaster\Actions\ConductStockCount;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemCategory;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\ItemMaster\Models\StockCount;
use App\Modules\ItemMaster\Models\StockCountLine;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Physical inventory over the API (Inventory spec §12).
 *
 * The one rule this controller enforces on its own is the blind count:
 * while the sheet is being walked, `system_qty` and the variance are left
 * out of the payload entirely rather than merely hidden by the screen.
 * A number the client never receives cannot be copied into the box next to
 * it, and that is the whole point of counting blind.
 */
class StockCountApiController extends ApiController
{
    public const VIEW = 'itemmaster.count.view_any';

    public const CREATE = 'itemmaster.count.create';

    public const POST = 'itemmaster.count.post';

    private const FILTERS = ['status', 'count_type', 'store_id', 'factory_id'];

    private const SORTS = ['count_no', 'count_date', 'status', 'variance_value'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ConductStockCount $counts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = StockCount::query()
            ->with(['factory:id,name', 'store:id,name,code'])
            ->withCount('lines')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $query->whereRaw('LOWER(count_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        if ($request->boolean('open')) {
            $query->whereIn('status', ['COUNTING', 'UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED']);
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'count_date');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (StockCount $count): array => $this->summary($count),
        );
    }

    public function formOptions(): JsonResponse
    {
        $this->allow(self::CREATE);

        $factoryIds = $this->context->accessibleFactoryIds();

        return ApiResponse::ok([
            'factories' => Factory::whereIn('id', $factoryIds)->orderBy('name')->get(['id', 'name'])->all(),
            'stores' => Store::query()->where('active', true)->with('warehouse:id,name,factory_id')->orderBy('name')->get()
                ->filter(fn (Store $store): bool => in_array($store->warehouse?->factory_id, $factoryIds, true))
                ->map(fn (Store $store): array => [
                    'id' => $store->id,
                    'name' => $store->name,
                    'code' => $store->code,
                    'store_type' => $store->store_type,
                    'factory_id' => $store->warehouse?->factory_id,
                ])->values()->all(),
            'item_departments' => ItemDepartment::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->all(),
            'item_categories' => ItemCategory::query()->orderBy('name')->get(['id', 'name'])->all(),
            'count_types' => StockCount::TYPES,
        ]);
    }

    public function show(StockCount $count): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($count);

        return ApiResponse::ok($this->detail($count));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::CREATE);

        $data = $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
            'store_id' => ['required', 'string', 'size:26'],
            'count_type' => ['nullable', Rule::in(StockCount::TYPES)],
            'is_blind' => ['nullable', 'boolean'],
            'count_date' => ['nullable', 'date'],
            'item_department_id' => ['nullable', 'string', 'size:26'],
            'item_category_id' => ['nullable', 'string', 'size:26'],
            'bin_ids' => ['nullable', 'array'],
            'bin_ids.*' => ['string', 'size:26'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['string', 'size:26'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        $store = Store::findOrFail($data['store_id']);

        return ApiResponse::created($this->detail($this->counts->open(
            Factory::findOrFail($data['factory_id']),
            $store,
            $data,
            $this->caller()->auditUserId(),
        )));
    }

    /** What the counters found, a batch of shelves at a time. */
    public function record(Request $request, StockCount $count): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($count);

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'string', 'size:26'],
            'lines.*.counted_qty' => ['required', 'numeric', 'gte:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::ok($this->detail($this->counts->record(
            $count, $data['lines'], $this->caller()->auditUserId(),
        )));
    }

    /** Stock on a shelf the system had no row for at all. */
    public function recordFound(Request $request, StockCount $count): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($count);

        $data = $request->validate([
            'item_id' => ['required', 'string', 'size:26'],
            'bin_id' => ['required', 'string', 'size:26'],
            'counted_qty' => ['required', 'numeric', 'gt:0'],
            'lot_no' => ['nullable', 'string', 'max:64'],
        ]);

        $this->counts->recordFound(
            $count,
            Item::findOrFail($data['item_id']),
            Bin::findOrFail($data['bin_id']),
            (string) $data['counted_qty'],
            $data['lot_no'] ?? null,
            $this->caller()->auditUserId(),
        );

        return ApiResponse::created($this->detail($count->fresh()));
    }

    public function recount(Request $request, StockCountLine $line): JsonResponse
    {
        $this->allow(self::CREATE);

        $count = StockCount::findOrFail($line->stock_count_id);
        $this->assertReachable($count);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->counts->flagForRecount($line, $data['reason'] ?? null);

        return ApiResponse::ok($this->detail($count->fresh()));
    }

    public function submit(StockCount $count): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($count);

        return ApiResponse::ok($this->detail($this->counts->submit($count, $this->caller()->auditUserId())));
    }

    public function post(StockCount $count): JsonResponse
    {
        $this->allow(self::POST);
        $this->assertReachable($count);

        return ApiResponse::ok($this->detail($this->counts->post($count, $this->caller()->auditUserId())));
    }

    public function cancel(Request $request, StockCount $count): JsonResponse
    {
        $this->allow(self::CREATE);
        $this->assertReachable($count);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return ApiResponse::ok($this->detail($this->counts->cancel($count, $data['reason'] ?? null)));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(StockCount $count): array
    {
        return [
            'id' => $count->id,
            'count_no' => $count->count_no,
            'count_type' => $count->count_type,
            'count_date' => $count->count_date?->toDateString(),
            'status' => $count->status,
            'is_blind' => (bool) $count->is_blind,
            'variance_value' => (string) $count->variance_value,
            'line_count' => $count->lines_count ?? null,
            'factory' => $count->factory === null ? null : ['id' => $count->factory->id, 'name' => $count->factory->name],
            'store' => $count->store === null ? null : ['id' => $count->store->id, 'name' => $count->store->name, 'code' => $count->store->code],
            'created_at' => $count->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(StockCount $count): array
    {
        $count->loadMissing([
            'factory:id,name', 'store:id,name,code', 'creator:id,name', 'poster:id,name',
            'lines.uom:id,code,name', 'lines.bin:id,code,name', 'lines.counter:id,name',
        ]);

        $mayCount = $this->caller()->can(self::CREATE);
        // While the sheet is being walked, the system figure is withheld
        // from the payload itself on a blind count — see the class note.
        $reveal = $count->revealsSystemQty();

        return $this->summary($count) + [
            'scope' => $count->scope_json,
            'notes' => $count->notes,
            'approval_status' => $count->approval_status,
            'approval_request_id' => $count->approval_request_id,
            'created_by' => $count->creator === null ? null : ['id' => $count->creator->id, 'name' => $count->creator->name],
            'posted_by' => $count->poster === null ? null : ['id' => $count->poster->id, 'name' => $count->poster->name],
            'submitted_at' => $count->submitted_at?->toIso8601String(),
            'approved_at' => $count->approved_at?->toIso8601String(),
            'rejected_at' => $count->rejected_at?->toIso8601String(),
            'posted_at' => $count->posted_at?->toIso8601String(),
            'cancelled_at' => $count->cancelled_at?->toIso8601String(),
            'reveals_system_qty' => $reveal,
            'uncounted_count' => $count->lines->filter(fn (StockCountLine $line): bool => $line->isUncounted())->count(),
            'variance_count' => $count->lines->where('status', 'VARIANCE')->count(),
            'can_record' => $count->isCounting() && $mayCount,
            'can_submit' => $count->canTransitionTo('UNDER_REVIEW') && $mayCount,
            'can_post' => $count->isPostable() && $this->caller()->can(self::POST),
            'can_cancel' => $count->canTransitionTo('CANCELLED') && $mayCount,
            'lines' => $count->lines->map(fn (StockCountLine $line): array => $this->line($line, $reveal))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(StockCountLine $line, bool $reveal): array
    {
        $payload = [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'item_code' => $line->item_code,
            'item_name' => $line->item_name,
            'uom' => $line->uom === null ? null : ['code' => $line->uom->code, 'name' => $line->uom->name],
            'bin' => $line->bin === null ? null : ['id' => $line->bin->id, 'code' => $line->bin->code, 'name' => $line->bin->name],
            'lot_no' => $line->lot_no,
            'counted_qty' => $line->counted_qty === null ? null : (string) $line->counted_qty,
            'status' => $line->status,
            'is_unexpected' => (bool) $line->is_unexpected,
            'counted_by' => $line->counter?->name,
            'counted_at' => $line->counted_at?->toIso8601String(),
            'notes' => $line->notes,
            'item_stock_transaction_id' => $line->item_stock_transaction_id,
        ];

        if (! $reveal) {
            return $payload;
        }

        return $payload + [
            'system_qty' => (string) $line->system_qty,
            'variance_qty' => (string) $line->variance_qty,
            'unit_cost' => (string) $line->unit_cost,
            'variance_value' => (string) $line->variance_value,
        ];
    }

    private function assertReachable(StockCount $count): void
    {
        if (! $this->context->canAccessFactory((string) $count->factory_id)) {
            abort(404);
        }
    }
}
