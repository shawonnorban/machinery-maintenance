<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\AccBooking;
use App\Modules\Merchandise\Models\AccBookingLine;
use App\Modules\Merchandise\Models\AccRequisition;
use App\Modules\Merchandise\Models\AccRequisitionLine;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Support\TrimOrders;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Accessories & Trims → the accessory bookings (AnnotechERP 06/08), as
 * Brickland works them: Trims Accessories Booking and the Booking Register
 * list the confirmed orders, and picking one shows its bookings and books
 * another (PO #, subject, supplier, req type, PO and delivery dates);
 * Accessories Booking Lookup lists every booking, and its Booked Trims panel
 * adds trim lines, adjusts them in place and removes them. Numbered
 * ACB-00001. The Accessories Purchase Register lists the purchase
 * requisitions raised for accessories.
 */
class AccBookingApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok([
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'items' => MaterialItem::query()->where('kind', 'TRIMS')->where('active', true)->orderBy('name')->get(['id', 'name', 'item_code', 'uom'])
                ->map(fn ($i): array => ['value' => $i->id, 'label' => $i->item_code ? "{$i->name} ({$i->item_code})" : $i->name, 'name' => $i->name, 'uom' => $i->uom])->values()->all(),
            'req_types' => AccBooking::REQ_TYPES, 'statuses' => AccBooking::STATUSES,
        ]);
    }

    /** Trims Accessories Booking / Booking Register: the confirmed orders, each with how many bookings it has. */
    public function orders(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $orders = TrimOrders::open();
        $counts = AccBooking::query()->whereIn('order_id', $orders->pluck('id'))->selectRaw('order_id, count(*) as n')->groupBy('order_id')->pluck('n', 'order_id');

        return ApiResponse::ok(collect(TrimOrders::rows($orders))->map(fn (array $o): array => $o + ['bookings' => (int) ($counts[$o['order_id']] ?? 0)])->values()->all());
    }

    /** Accessories Booking Lookup: every booking with its order, PO, supplier, trim count and status. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = AccBooking::query()->orderByDesc('booking_no')->get();
        $orders = TrimOrders::rows(Order::query()->whereIn('id', $rows->pluck('order_id'))->get());
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id')->filter())->pluck('name', 'id');
        $lines = AccBookingLine::query()->whereIn('booking_id', $rows->pluck('id'))->selectRaw('booking_id, count(*) as n')->groupBy('booking_id')->pluck('n', 'booking_id');

        return ApiResponse::ok($rows->map(fn (AccBooking $b): array => $this->summary($b, $orders[$b->order_id] ?? null, $suppliers, (int) ($lines[$b->id] ?? 0)))->values()->all());
    }

    /** The bookings raised against one order (by its order no). */
    public function forOrder(string $order): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $row = Order::query()->where('order_no', $order)->firstOrFail();

        return ApiResponse::ok($this->bookingsOf($row));
    }

    /** Book accessories: a booking against the order — its trims are added on the Booked Trims panel. */
    public function store(Request $request, string $order): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = Order::query()->where('order_no', $order)->firstOrFail();
        $company = $this->caller()->companyId;
        $data = $request->validate([
            'po_no' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:200'],
            'supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'req_type' => ['required', Rule::in(AccBooking::REQ_TYPES)],
            'po_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:po_date'],
        ], [
            'req_type.required' => __('merchandise.acc_po.required', ['field' => __('merchandise.master.acb.f.req_type')]),
            'delivery_date.after_or_equal' => __('merchandise.acb.delivery_after'),
        ]);
        $text = fn (string $k): ?string => trim((string) ($data[$k] ?? '')) ?: null;
        DB::transaction(function () use ($row, $data, $text): void {
            AccBooking::query()->create([
                'booking_no' => $this->nextNumber(), 'order_id' => $row->id, 'po_no' => $text('po_no'), 'subject' => $text('subject'), 'supplier_id' => $data['supplier_id'] ?? null,
                'req_type' => $data['req_type'], 'po_date' => $data['po_date'] ?? null, 'delivery_date' => $data['delivery_date'] ?? null, 'status' => 'DRAFT',
                'created_by' => $this->caller()->auditUserId(),
            ]);
        });

        return ApiResponse::created($this->bookingsOf($row));
    }

    /** Booked Trims: one booking and its trim lines. */
    public function show(string $booking): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->present($this->find($booking)));
    }

    /** Add trim line: kept at once. */
    public function addLine(Request $request, string $booking): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($booking);
        $data = $this->validateLine($request->all(), '');
        $last = (int) AccBookingLine::query()->where('booking_id', $row->id)->max('sort_order');
        AccBookingLine::query()->create($data + ['booking_id' => $row->id, 'status' => 'DRAFT', 'sort_order' => $last + 1]);

        return ApiResponse::created($this->present($row));
    }

    /** Save: the lines as adjusted in place; a line left out was removed there. */
    public function saveLines(Request $request, string $booking): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($booking);
        $request->validate(['lines' => ['present', 'array', 'max:300'], 'lines.*.id' => ['required', 'string']]);
        $lines = array_values($request->input('lines', []));
        $mine = AccBookingLine::query()->where('booking_id', $row->id)->get()->keyBy('id');
        $errors = [];
        $clean = [];
        foreach ($lines as $i => $l) {
            if (! $mine->has($l['id'])) {
                $errors["lines.{$i}.id"] = __('merchandise.acb.row_foreign', ['n' => $i + 1]);

                continue;
            }
            try {
                $clean[$l['id']] = $this->validateLine($l, __('merchandise.acb.row', ['n' => $i + 1]), $mine[$l['id']]);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $k => $m) {
                    $errors["lines.{$i}.{$k}"] = $m[0];
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($row, $mine, $clean): void {
            AccBookingLine::query()->where('booking_id', $row->id)->whereNotIn('id', array_keys($clean))->delete();
            foreach ($clean as $id => $data) {
                $mine[$id]->update($data);
            }
        });

        return ApiResponse::ok($this->present($row));
    }

    /** Accessories Purchase Register: the accessory purchase requisitions with their order, ship date, items and status. */
    public function purchaseRegister(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = AccRequisition::query()->where('dummy', false)->orderByDesc('req_date')->orderByDesc('req_no')->get();
        $orders = TrimOrders::rows(Order::query()->whereIn('id', $rows->pluck('order_id'))->get());
        $lines = AccRequisitionLine::query()->whereIn('requisition_id', $rows->pluck('id'))->orderBy('sort_order')->get(['requisition_id', 'description'])->groupBy('requisition_id');

        return ApiResponse::ok($rows->map(function (AccRequisition $r) use ($orders, $lines): array {
            $o = $orders[$r->order_id] ?? null;

            return [
                'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'req_month' => $r->req_date->format('Y-m'), 'ship_date' => $o['ship_date'] ?? null,
                'buyer' => $o['buyer'] ?? null, 'job_no' => $o['job_no'] ?? null, 'work_style' => $o['work_style'] ?? null, 'order_no' => $o['order_no'] ?? null,
                'items' => ($lines[$r->id] ?? collect())->pluck('description')->implode(', '), 'status' => $r->status,
            ];
        })->values()->all());
    }

    private function find(string $booking): AccBooking
    {
        return AccBooking::query()->where('booking_no', $booking)->firstOrFail();
    }

    /**
     * One trim line, the description, colour, size, quantity, UOM and rate.
     *
     * @return array<string, mixed>
     */
    private function validateLine(array $input, string $row, ?AccBookingLine $line = null): array
    {
        $company = $this->caller()->companyId;
        $data = validator($input, [
            'item_id' => ['nullable', Rule::exists('mp_items', 'id')->where('company_id', $company)],
            'description' => ['required', 'string', 'max:200'],
            'colour' => ['nullable', 'string', 'max:80'],
            'size' => ['nullable', 'string', 'max:40'],
            'qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->caller()->companyId)],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], [
            'description.required' => trim("{$row} ".__('merchandise.acc_po.required', ['field' => __('merchandise.master.acb.col.description')])),
            'qty.required' => trim("{$row} ".__('merchandise.acc_po.required', ['field' => __('merchandise.master.acb.col.qty')])),
            'qty.gt' => trim("{$row} ".__('merchandise.acb.qty_positive')),
        ])->validate();
        $text = fn (string $k): ?string => trim((string) ($data[$k] ?? '')) ?: null;

        return [
            // A line adjusted in place keeps its trim; a new one names it (or not).
            'item_id' => $line ? $line->item_id : ($data['item_id'] ?? null), 'description' => trim($data['description']), 'colour' => $text('colour'), 'size' => $text('size'),
            'qty' => round((float) $data['qty'], 4), 'uom' => UomCodes::normalize($data['uom'] ?? null), 'rate' => isset($data['rate']) ? round((float) $data['rate'], 4) : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function bookingsOf(Order $order): array
    {
        $rows = AccBooking::query()->where('order_id', $order->id)->orderBy('booking_no')->get();
        $orders = TrimOrders::rows(collect([$order]));
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id')->filter())->pluck('name', 'id');
        $lines = AccBookingLine::query()->whereIn('booking_id', $rows->pluck('id'))->selectRaw('booking_id, count(*) as n')->groupBy('booking_id')->pluck('n', 'booking_id');

        return $rows->map(fn (AccBooking $b): array => $this->summary($b, $orders[$order->id], $suppliers, (int) ($lines[$b->id] ?? 0)))->values()->all();
    }

    /** @return array<string, mixed> */
    private function summary(AccBooking $b, ?array $order, $suppliers, int $items): array
    {
        return [
            'booking_no' => $b->booking_no, 'order_no' => $order['order_no'] ?? null, 'buyer' => $order['buyer'] ?? null, 'style_desc' => $order['style_desc'] ?? null,
            'style_no' => $order['style_no'] ?? null, 'order_type' => $order['order_type'] ?? null, 'order_qty' => $order['qty'] ?? null, 'ship_date' => $order['ship_date'] ?? null,
            'po_no' => $b->po_no, 'subject' => $b->subject, 'po_date' => $b->po_date?->toDateString(), 'delivery_date' => $b->delivery_date?->toDateString(),
            'supplier' => $b->supplier_id ? ($suppliers[$b->supplier_id] ?? null) : null, 'req_type' => $b->req_type, 'items' => $items, 'status' => $b->status,
        ];
    }

    /** @return array<string, mixed> */
    private function present(AccBooking $b): array
    {
        $lines = AccBookingLine::query()->where('booking_id', $b->id)->orderBy('sort_order')->get();
        $items = MaterialItem::query()->whereIn('id', $lines->pluck('item_id')->filter())->pluck('name', 'id');

        return [
            'booking_no' => $b->booking_no, 'po_no' => $b->po_no, 'status' => $b->status, 'editable' => true,
            'lines' => $lines->map(fn (AccBookingLine $l): array => [
                'id' => $l->id, 'item' => $l->item_id ? ($items[$l->item_id] ?? null) : null, 'description' => $l->description, 'colour' => $l->colour, 'size' => $l->size,
                'qty' => $l->qty, 'uom' => $l->uom, 'rate' => $l->rate, 'status' => $l->status,
            ])->values()->all(),
        ];
    }

    /** ACB-00001: the company's next booking; held to the end of the save. */
    private function nextNumber(): string
    {
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_acc_bookings:'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) AccBooking::query()->pluck('booking_no')->map(fn (string $v): int => (int) substr($v, 4))->max();

        return 'ACB-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
