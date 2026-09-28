<?php

declare(strict_types=1);

namespace App\Modules\Production\Http\Controllers\Api;

use App\Modules\Production\Actions\ManageOrder;
use App\Modules\Production\Models\Order;
use App\Modules\Production\Models\Style;
use App\Modules\Production\Models\StyleBomLine;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Orders, styles and bills of materials over the wire (Master Data spec §6).
 *
 * The minimal production master this scope owns because no merchandising
 * module exists to read it from. Read access is wide — the store needs to
 * see what a programme consumes — while editing sits with whoever plans
 * production.
 */
class ProductionOrderApiController extends ApiController
{
    public const VIEW = 'production.order.view_any';

    public const MANAGE = 'production.order.manage';

    private const FILTERS = ['status', 'factory_id', 'buyer'];

    private const SORTS = ['order_no', 'buyer', 'ship_date', 'status', 'created_at'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly ManageOrder $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->allow(self::VIEW);

        $query = Order::query()
            ->with(['factory:id,name'])
            ->withCount('styles')
            ->whereIn('factory_id', $this->context->accessibleFactoryIds());

        if (is_string($search = $request->query('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(order_no) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(buyer) LIKE ?', [$needle]));
        }

        $query = $this->applyFilters($query, $request, self::FILTERS);
        $query = $this->applySort($query, $request, self::SORTS, 'created_at');

        return ApiResponse::paginated(
            $query->paginate($this->perPage($request))->withQueryString(),
            fn (Order $order): array => $this->summary($order),
        );
    }

    public function show(Order $order): JsonResponse
    {
        $this->allow(self::VIEW);
        $this->assertReachable($order);

        return ApiResponse::ok($this->detail($order));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow(self::MANAGE);

        $data = $request->validate([
            'factory_id' => ['required', 'string', 'size:26'],
            'order_no' => ['required', 'string', 'max:64'],
            'buyer' => ['required', 'string', 'max:160'],
            'order_qty' => ['required', 'numeric', 'gt:0'],
            'order_date' => ['nullable', 'date'],
            'ship_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->context->canAccessFactory($data['factory_id'])) {
            abort(403);
        }

        return ApiResponse::created($this->detail($this->orders->create(
            Factory::findOrFail($data['factory_id']), $data, $this->caller()->auditUserId(),
        )));
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($order);

        $data = $request->validate([
            'order_no' => ['sometimes', 'string', 'max:64'],
            'buyer' => ['sometimes', 'string', 'max:160'],
            'order_qty' => ['sometimes', 'numeric', 'gt:0'],
            'order_date' => ['nullable', 'date'],
            'ship_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::ok($this->detail($this->orders->update($order, $data)));
    }

    public function setStatus(Request $request, Order $order): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($order);

        $data = $request->validate(['status' => ['required', Rule::in(Order::STATUSES)]]);

        return ApiResponse::ok($this->detail($this->orders->setStatus($order, $data['status'])));
    }

    public function storeStyle(Request $request, Order $order): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable($order);

        $data = $request->validate([
            'style_no' => ['required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:64'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->orders->addStyle($order, $data);

        return ApiResponse::created($this->detail($order->fresh()));
    }

    /** The whole bill of materials, replaced in one call. */
    public function saveBom(Request $request, Style $style): JsonResponse
    {
        $this->allow(self::MANAGE);
        $this->assertReachable(Order::findOrFail($style->production_order_id));

        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'string', 'size:26'],
            'lines.*.uom_id' => ['nullable', 'string', 'size:26'],
            'lines.*.consumption_per_unit' => ['required', 'numeric', 'gt:0'],
            'lines.*.wastage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->orders->saveBom($style, $data['lines']);

        return ApiResponse::ok($this->detail(Order::findOrFail($style->production_order_id)));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'buyer' => $order->buyer,
            'order_qty' => (string) $order->order_qty,
            'order_date' => $order->order_date?->toDateString(),
            'ship_date' => $order->ship_date?->toDateString(),
            'status' => $order->status,
            'factory' => $order->factory === null ? null : ['id' => $order->factory->id, 'name' => $order->factory->name],
            'style_count' => $order->styles_count ?? ($order->relationLoaded('styles') ? $order->styles->count() : null),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Order $order): array
    {
        $order->loadMissing([
            'factory:id,name', 'creator:id,name',
            'styles.bomLines.uom:id,code,name', 'styles.cuttingPrograms',
        ]);

        $mayManage = $this->caller()->can(self::MANAGE);

        return $this->summary($order) + [
            'notes' => $order->notes,
            'created_by' => $order->creator === null ? null : ['id' => $order->creator->id, 'name' => $order->creator->name],
            'can_manage' => $mayManage && $order->isOpen(),
            'styles' => $order->styles->map(fn (Style $style): array => [
                'id' => $style->id,
                'style_no' => $style->style_no,
                'description' => $style->description,
                'color' => $style->color,
                'quantity' => (string) $style->quantity,
                'programmed_qty' => $style->cuttingPrograms
                    ->where('status', '!=', 'CANCELLED')
                    ->reduce(fn (string $sum, $p): string => bcadd($sum, (string) $p->program_qty, 4), '0.0000'),
                'bom' => $style->bomLines->map(fn (StyleBomLine $line): array => [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_name' => $line->item_name,
                    'uom' => $line->uom === null ? null : ['id' => $line->uom->id, 'code' => $line->uom->code, 'name' => $line->uom->name],
                    'consumption_per_unit' => (string) $line->consumption_per_unit,
                    'wastage_percent' => (string) $line->wastage_percent,
                    'notes' => $line->notes,
                ])->all(),
            ])->all(),
        ];
    }

    private function assertReachable(Order $order): void
    {
        if (! $this->context->canAccessFactory((string) $order->factory_id)) {
            abort(404);
        }
    }
}
