<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\TrimSourcing;
use App\Modules\Merchandise\Support\TrimOrders;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Accessories & Trims → Trims & Accessories Sourcing (AnnotechERP 06/08), as
 * Brickland works it: one sourcing line per trim of a confirmed order — the
 * trim, its description, the supplier chosen and whether the buyer nominated
 * it (a nominated supplier is not substituted without the buyer's word), a
 * status and remarks. Records lists every line; Details shows an order's
 * lines, where the supplier, nomination and status are set; New sourcing
 * line raises one.
 */
class TrimSourcingApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok([
            'orders' => collect(TrimOrders::rows(TrimOrders::open()))->map(fn (array $o): array => ['value' => $o['order_id'], 'label' => $o['label'], 'order_no' => $o['order_no']])->values()->all(),
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'items' => MaterialItem::query()->where('kind', 'TRIMS')->where('active', true)->orderBy('name')->get(['id', 'name', 'item_code'])
                ->map(fn ($i): array => ['value' => $i->id, 'label' => $i->item_code ? "{$i->name} ({$i->item_code})" : $i->name, 'name' => $i->name])->values()->all(),
            'statuses' => TrimSourcing::STATUSES,
        ]);
    }

    /** Records: every sourcing line with its order (buyer, style, type, quantity, dates, lead time), trim and supplier. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = TrimSourcing::query()->orderBy('created_at')->get();
        $orders = TrimOrders::rows(Order::query()->whereIn('id', $rows->pluck('order_id'))->get());
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id')->filter())->pluck('name', 'id');

        $out = $rows->map(fn (TrimSourcing $s): array => $this->present($s, $orders[$s->order_id] ?? [], $suppliers))->sortBy([['ship_date', 'asc'], ['order_no', 'asc']]);

        return ApiResponse::ok($out->values()->all());
    }

    /** Details: the confirmed orders, each with how many trims are sourced. */
    public function orders(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $orders = TrimOrders::open();
        $counts = TrimSourcing::query()->whereIn('order_id', $orders->pluck('id'))->selectRaw('order_id, count(*) as n')->groupBy('order_id')->pluck('n', 'order_id');

        return ApiResponse::ok(collect(TrimOrders::rows($orders))->map(fn (array $o): array => $o + ['lines' => (int) ($counts[$o['order_id']] ?? 0)])->values()->all());
    }

    /** One order's sourcing lines (by its order no). */
    public function forOrder(string $order): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->linesOf(Order::query()->where('order_no', $order)->firstOrFail()));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request, true);
        $row = TrimSourcing::query()->create($data + ['created_by' => $this->caller()->auditUserId()]);

        return ApiResponse::created($this->linesOf(Order::query()->whereKey($row->order_id)->firstOrFail()));
    }

    /** Details: the supplier, nomination, status and remarks set on a line. */
    public function update(Request $request, TrimSourcing $sourcing): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request, false);
        // A buyer-nominated supplier stays unless the buyer agreed (the line is first un-nominated).
        if ($sourcing->nominated && ($data['nominated'] ?? true) && $sourcing->supplier_id && ($data['supplier_id'] ?? null) !== $sourcing->supplier_id) {
            throw ValidationException::withMessages(['supplier_id' => __('merchandise.tsr.nominated_locked')]);
        }
        $sourcing->update($data);

        return ApiResponse::ok($this->linesOf(Order::query()->whereKey($sourcing->order_id)->firstOrFail()));
    }

    public function destroy(TrimSourcing $sourcing): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $order = Order::query()->whereKey($sourcing->order_id)->firstOrFail();
        $sourcing->delete();

        return ApiResponse::ok($this->linesOf($order));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating): array
    {
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'order_id' => [$creating ? 'required' : 'prohibited', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'item_id' => ['nullable', Rule::exists('mp_items', 'id')->where('company_id', $company)],
            'description' => ['required', 'string', 'max:200'],
            'supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'nominated' => ['sometimes', 'boolean'],
            'status' => ['nullable', Rule::in(TrimSourcing::STATUSES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], collect(['order_id', 'description'])->mapWithKeys(fn (string $f) => ["{$f}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.tsr.f.{$f}")])])->all());
        if (! empty($data['nominated']) && empty($data['supplier_id'])) {
            throw ValidationException::withMessages(['supplier_id' => __('merchandise.tsr.nominated_needs_supplier')]);
        }

        return array_filter([
            'order_id' => $data['order_id'] ?? null, 'item_id' => $data['item_id'] ?? null, 'description' => trim($data['description']), 'supplier_id' => $data['supplier_id'] ?? null,
            'nominated' => (bool) ($data['nominated'] ?? false), 'status' => $data['status'] ?? 'DRAFT', 'remarks' => trim((string) ($data['remarks'] ?? '')) ?: null,
        ], fn ($v, string $k) => $k !== 'order_id' || $v !== null, ARRAY_FILTER_USE_BOTH);
    }

    /** @return list<array<string, mixed>> */
    private function linesOf(Order $order): array
    {
        $rows = TrimSourcing::query()->where('order_id', $order->id)->orderBy('created_at')->get();
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id')->filter())->pluck('name', 'id');
        $o = TrimOrders::rows(collect([$order]))[$order->id];

        return $rows->map(fn (TrimSourcing $s): array => $this->present($s, $o, $suppliers))->values()->all();
    }

    /** @return array<string, mixed> */
    private function present(TrimSourcing $s, array $order, $suppliers): array
    {
        $item = $s->item_id ? MaterialItem::query()->whereKey($s->item_id)->value('name') : null;

        return [
            'id' => $s->id, 'order_no' => $order['order_no'] ?? null, 'buyer' => $order['buyer'] ?? null, 'style_desc' => $order['style_desc'] ?? null, 'style_no' => $order['style_no'] ?? null,
            'work_style' => $order['work_style'] ?? null, 'order_type' => $order['order_type'] ?? null, 'order_qty' => $order['qty'] ?? null, 'conf_date' => $order['conf_date'] ?? null,
            'ship_date' => $order['ship_date'] ?? null, 'lead_time' => $order['lead_time'] ?? null, 'item_id' => $s->item_id, 'trim' => $item ?? $s->description,
            'description' => $s->description, 'supplier_id' => $s->supplier_id, 'supplier' => $s->supplier_id ? ($suppliers[$s->supplier_id] ?? null) : null,
            'nominated' => $s->nominated, 'status' => $s->status, 'remarks' => $s->remarks,
        ];
    }
}
