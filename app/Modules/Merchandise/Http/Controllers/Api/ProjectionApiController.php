<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Resources\OrderResource;
use App\Modules\Merchandise\Support\OrderNumber;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Style & Order → Projection & Call-Off Order (AnnotechERP 06/07). A
 * projection is an order of type Projection — a volume the buyer expects to
 * place; call-offs are the firm orders drawn down against it, each its own
 * order of the same style and buyer, numbered from the buyer's series. The
 * call-offs of a projection never add up to more than it projected.
 */
class ProjectionApiController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $projections = Order::query()->where('order_type', 'PROJECTION')->orderByDesc('conf_date')->orderByDesc('order_no')->get();
        $drawn = $this->drawn($projections->pluck('id')->all());
        $resource = app(OrderResource::class);

        return ApiResponse::ok($projections->map(fn (Order $p): array => $resource->present($p) + $this->balance($p, $drawn[$p->id] ?? null))->values()->all());
    }

    public function callOffs(string $order): JsonResponse
    {
        $this->allow('merchandise.order.view');
        $projection = $this->projection($order);

        return ApiResponse::ok($this->present($projection));
    }

    /** A call-off: a firm order against the projection, never more than its balance. */
    public function store(Request $request, string $order): JsonResponse
    {
        $this->allow('merchandise.order.manage');
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'job_no' => ['required', 'string', 'max:64'],
            'po_no' => ['nullable', 'string', 'max:64'],
            'conf_date' => ['required', 'date'],
            'ship_date' => ['required', 'date', 'after_or_equal:conf_date'],
            'total_qty' => ['required', 'integer', 'min:1', 'max:100000000'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'ship_country_id' => ['nullable', Rule::exists('origin_countries', 'id')->where('company_id', $company)],
        ], [
            'job_no.required' => __('merchandise.order.job_required'),
            'ship_date.after_or_equal' => __('merchandise.order_confirmation.ship_before_conf'),
            'total_qty.required' => __('merchandise.order.qty_required'),
        ]);

        $projection = DB::transaction(function () use ($order, $data): Order {
            // The projection row is held, so two clerks never draw past its balance.
            $projection = Order::query()->whereKey($order)->where('order_type', 'PROJECTION')->lockForUpdate()->firstOrFail();
            $left = $projection->total_qty - (int) Order::query()->where('projection_id', $projection->id)->where('status', '!=', 'CANCELLED')->sum('total_qty');
            if ($data['total_qty'] > $left) {
                throw ValidationException::withMessages(['total_qty' => __('merchandise.projection.over_balance', ['left' => max(0, $left), 'no' => $projection->order_no])]);
            }
            Order::query()->create([
                'order_no' => OrderNumber::next($projection->buyer_id),
                'job_no' => trim($data['job_no']),
                'po_no' => isset($data['po_no']) && trim($data['po_no']) !== '' ? trim($data['po_no']) : null,
                'projection_id' => $projection->id,
                'order_type' => 'BULK_NEW',
                'status' => 'CONFIRMED',
                'order_date' => $data['conf_date'],
                'conf_date' => $data['conf_date'],
                'ship_date' => $data['ship_date'],
                'total_qty' => $data['total_qty'],
                'unit_price' => $data['unit_price'] ?? $projection->unit_price,
                'ship_country_id' => $data['ship_country_id'] ?? $projection->ship_country_id,
                'created_by' => $this->caller()->auditUserId(),
            ] + $projection->only(['style_id', 'style_no', 'work_style_no', 'sub_style', 'buyer_id', 'buyer_office_id', 'factory_id', 'item', 'gmt_type', 'unit', 'pcs_per_unit', 'currency', 'exchange_rate', 'inquiry_id', 'tna_template_id']));

            return $projection;
        });

        return ApiResponse::created($this->present($projection));
    }

    private function projection(string $order): Order
    {
        return Order::query()->whereKey($order)->where('order_type', 'PROJECTION')->firstOrFail();
    }

    /** @return array<string, mixed> the projection, its balance and its call-offs */
    private function present(Order $projection): array
    {
        $resource = app(OrderResource::class);
        $calls = Order::query()->where('projection_id', $projection->id)->orderBy('conf_date')->orderBy('order_no')->get();

        return [
            'projection' => $resource->present($projection) + $this->balance($projection, $this->drawn([$projection->id])[$projection->id] ?? null),
            'call_offs' => $calls->map(fn (Order $o): array => $resource->present($o))->values()->all(),
        ];
    }

    /** @param list<string> $ids @return array<string, object{n: int, qty: int}> what the call-offs of each projection drew, cancelled ones aside */
    private function drawn(array $ids): array
    {
        return Order::query()->whereIn('projection_id', $ids)->where('status', '!=', 'CANCELLED')
            ->selectRaw('projection_id, count(*) as n, coalesce(sum(total_qty), 0) as qty')->groupBy('projection_id')->get()->keyBy('projection_id')->all();
    }

    /** @return array{call_offs: int, called_off: int, balance: int} */
    private function balance(Order $projection, ?object $drawn): array
    {
        $qty = (int) ($drawn->qty ?? 0);

        return ['call_offs' => (int) ($drawn->n ?? 0), 'called_off' => $qty, 'balance' => $projection->total_qty - $qty];
    }
}
