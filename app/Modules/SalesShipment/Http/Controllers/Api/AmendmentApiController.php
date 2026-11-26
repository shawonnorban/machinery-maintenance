<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Http\Controllers\Api;

use App\Modules\SalesShipment\Models\SsAmendment;
use App\Modules\SalesShipment\Models\SsAmendmentLine;
use App\Modules\SalesShipment\Models\SsSalesOrder;
use App\Modules\SalesShipment\Models\SsSalesOrderSize;
use App\Modules\SalesShipment\Support\SalesBook;
use App\Modules\SalesShipment\Support\SsSettings;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales & Shipment → Order & Delivery → Sales Order Amendment (AnnotechERP
 * 21/02): controlled changes to a confirmed order — size by size the current
 * and revised quantity and rate, and the revised delivery and shipment dates,
 * with the reason. Drafted (one open amendment per order), submitted,
 * approved or rejected, then applied: the revised figures are written onto
 * the order, which moves to its next version, and the order as it stood is
 * kept as a snapshot. A size never goes below what has already gone out.
 */
class AmendmentApiController extends ApiController
{
    private ?SalesBook $book = null;

    public function index(): JsonResponse
    {
        $this->allow('salesshipment.order.view');
        $b = $this->book();
        $rows = SsAmendment::query()->orderByDesc('amend_date')->orderByDesc('amend_no')->get();
        $orders = DB::table('ss_sales_orders')->whereIn('id', $rows->pluck('order_id'))->get(['id', 'so_no', 'customer_id'])->keyBy('id');
        $lines = DB::table('ss_amendment_lines')->whereIn('amendment_id', $rows->pluck('id'))->get()->groupBy('amendment_id');
        $sizes = DB::table('ss_sales_order_sizes')->whereIn('order_id', $rows->pluck('order_id'))->get(['id', 'order_id', 'qty', 'rate'])->groupBy('order_id');
        $names = $b->customerNames();

        return ApiResponse::ok($rows->map(function (SsAmendment $a) use ($orders, $lines, $sizes, $names, $b) {
            $o = $orders[$a->order_id] ?? null;
            $mine = $lines->get($a->id, collect());
            // The whole order before and after: an applied amendment reads its snapshot, an open one today's order.
            $before = $a->state === 'APPLIED' && $a->snapshot ? collect($a->snapshot['sizes'])->map(fn ($s) => (object) $s) : $sizes->get($a->order_id, collect());
            if ($a->state !== 'APPLIED') {
                $before = $before->map(fn ($s) => ($l = $mine->firstWhere('size_id', $s->id)) ? (object) ['id' => $s->id, 'qty' => $l->prev_qty, 'rate' => $l->prev_rate] : $s);
            }
            $after = $before->map(fn ($s) => ($l = $mine->firstWhere('size_id', $s->id)) ? (object) ['qty' => $l->revised_qty, 'rate' => $l->revised_rate] : $s);

            return [
                'amend_no' => $a->amend_no, 'amend_date' => $a->amend_date->toDateString(), 'so_no' => $o?->so_no, 'customer' => $names[$o?->customer_id] ?? '—', 'customer_id' => $o?->customer_id,
                'version' => $a->version, 'prev_qty' => (float) $before->sum('qty'), 'revised_qty' => (float) $after->sum('qty'),
                'prev_value' => round((float) $before->sum(fn ($s) => $s->qty * $s->rate), 2), 'revised_value' => round((float) $after->sum(fn ($s) => $s->qty * $s->rate), 2),
                'prev_shipment' => $a->prev_shipment_date->toDateString(), 'revised_shipment' => ($a->revised_shipment_date ?? $a->prev_shipment_date)->toDateString(),
                'reason' => $a->reason, 'requested_by' => $b->user($a->created_by), 'approved_by' => $b->trail($a->approved_by, $a->approved_at), 'state' => $a->state,
            ];
        })->values()->all());
    }

    public function show(string $no): JsonResponse
    {
        $this->allow('salesshipment.order.view');

        return ApiResponse::ok($this->present($this->find($no)));
    }

    /** The confirmed orders an amendment can be raised against, with each size's quantity and rate today. */
    public function orders(): JsonResponse
    {
        $this->allow('salesshipment.order.view');
        $names = $this->book()->customerNames();
        $orders = SsSalesOrder::query()->where('approval_status', 'CONFIRMED')->where('state', 'OPEN')->orderByDesc('receive_date')->orderByDesc('so_no')->get();
        $qty = DB::table('ss_sales_order_sizes')->whereIn('order_id', $orders->pluck('id'))->groupBy('order_id')->selectRaw('order_id, sum(qty) as q')->pluck('q', 'order_id');

        return ApiResponse::ok($orders->map(fn (SsSalesOrder $o) => ['so_no' => $o->so_no, 'customer' => $names[$o->customer_id] ?? '—', 'customer_id' => $o->customer_id,
            'qty' => (float) ($qty[$o->id] ?? 0), 'version' => $o->version])->values()->all());
    }

    public function source(string $so): JsonResponse
    {
        $this->allow('salesshipment.order.view');
        $o = SsSalesOrder::query()->where('so_no', $so)->firstOrFail();

        return ApiResponse::ok($this->sourceOf($o));
    }

    public function save(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('salesshipment.order.manage');
        $row = $no ? $this->find($no) : null;
        if ($row && $row->state !== 'DRAFT') {
            throw ValidationException::withMessages(['amend_no' => __('salesshipment.v.locked', ['no' => $row->amend_no, 'status' => __('salesshipment.am.state.'.$row->state)])]);
        }
        $data = $request->validate([
            'so_no' => ['required', 'string', 'max:24'], 'amend_date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:2000'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'revised_target_date' => ['required', 'date'], 'revised_shipment_date' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.size_id' => ['required', 'string', 'max:26', 'distinct'], 'lines.*.revised_qty' => ['required', 'numeric', 'min:0'],
            'lines.*.revised_rate' => ['required', 'numeric', 'min:0'],
        ], ['lines.required' => __('salesshipment.v.am_lines'), 'lines.min' => __('salesshipment.v.am_lines'), 'reason.required' => __('salesshipment.v.am_reason'),
            'lines.*.size_id.distinct' => __('salesshipment.v.am_size_twice')]);
        $order = SsSalesOrder::query()->where('so_no', $data['so_no'])->firstOrFail();
        if ($row && $row->order_id !== $order->id) {
            throw ValidationException::withMessages(['so_no' => __('salesshipment.v.am_order_fixed', ['no' => $row->amend_no])]);
        }
        if ($order->approval_status !== 'CONFIRMED' || $order->state !== 'OPEN') {
            throw ValidationException::withMessages(['so_no' => __('salesshipment.v.not_confirmed', ['no' => $order->so_no])]);
        }
        $open = SsAmendment::query()->where('order_id', $order->id)->whereIn('state', SsAmendment::OPEN)->when($row, fn ($q) => $q->where('id', '!=', $row->id))->value('amend_no');
        if ($open) {
            throw ValidationException::withMessages(['so_no' => __('salesshipment.v.am_open', ['no' => $order->so_no, 'amend' => $open])]);
        }
        $this->book()->assertOpen($data['amend_date'], 'amend_date');
        $lines = array_values($data['lines']);
        $this->checkFloor($order, $lines);
        $sizes = SsSalesOrderSize::query()->where('order_id', $order->id)->get()->keyBy('id');
        $head = ['order_id' => $order->id, 'amend_date' => $data['amend_date'], 'reason' => $data['reason'], 'remarks' => $data['remarks'] ?? null,
            'prev_target_date' => $order->delivery_target_date, 'revised_target_date' => $data['revised_target_date'], 'prev_shipment_date' => $order->shipment_date,
            'revised_shipment_date' => $data['revised_shipment_date'] ?? null, 'version' => $order->version + 1];
        $row = DB::transaction(function () use ($row, $head, $lines, $sizes, $data) {
            if ($row) {
                $row->update($head);
                SsAmendmentLine::query()->where('amendment_id', $row->id)->delete();
            } else {
                $row = SsAmendment::query()->create($head + ['amend_no' => SsSettings::nextNo('prefix_amendment', 'ss_amendments', 'amend_no', $data['amend_date'], $this->caller()->companyId),
                    'state' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($lines as $l) {
                $s = $sizes[$l['size_id']];
                SsAmendmentLine::query()->create(['amendment_id' => $row->id, 'size_id' => $s->id, 'prev_qty' => $s->qty, 'revised_qty' => $l['revised_qty'], 'prev_rate' => $s->rate, 'revised_rate' => $l['revised_rate']]);
            }

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()), [], $no ? 200 : 201);
    }

    /** Submit, Approve, Reject, Apply, Cancel. */
    public function act(string $no, string $action): JsonResponse
    {
        $from = ['submit' => ['DRAFT'], 'approve' => ['SUBMITTED'], 'reject' => ['SUBMITTED'], 'apply' => ['APPROVED'], 'cancel' => SsAmendment::OPEN];
        abort_unless(isset($from[$action]), 404);
        in_array($action, ['submit', 'cancel'], true) ? $this->allow('salesshipment.order.manage') : $this->allow('salesshipment.order.approve');
        $row = DB::transaction(function () use ($no, $action, $from) {
            $row = SsAmendment::query()->where('amend_no', $no)->lockForUpdate()->firstOrFail();
            if (! in_array($row->state, $from[$action], true)) {
                throw ValidationException::withMessages(['status' => __('salesshipment.v.step', ['no' => $row->amend_no, 'status' => __('salesshipment.am.state.'.$row->state)])]);
            }
            $this->book()->assertOpen($row->amend_date, 'status');
            $by = $this->caller()->auditUserId();
            match ($action) {
                'submit' => $row->update(['state' => 'SUBMITTED', 'submitted_at' => now(), 'submitted_by' => $by]),
                'approve' => $row->update(['state' => 'APPROVED', 'approved_at' => now(), 'approved_by' => $by]),
                'reject' => $row->update(['state' => 'REJECTED', 'rejected_at' => now(), 'rejected_by' => $by]),
                'cancel' => $row->update(['state' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $by]),
                'apply' => $this->apply($row, $by),
            };

            return $row;
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** Writes the revised figures onto the order, keeps it as it stood, and moves it to its next version. */
    private function apply(SsAmendment $a, ?string $by): void
    {
        $order = SsSalesOrder::query()->lockForUpdate()->findOrFail($a->order_id);
        if ($order->approval_status !== 'CONFIRMED' || $order->state !== 'OPEN') {
            throw ValidationException::withMessages(['status' => __('salesshipment.v.not_confirmed', ['no' => $order->so_no])]);
        }
        $lines = SsAmendmentLine::query()->where('amendment_id', $a->id)->get();
        $this->checkFloor($order, $lines->map(fn ($l) => ['size_id' => $l->size_id, 'revised_qty' => (float) $l->revised_qty])->all());
        $sizes = SsSalesOrderSize::query()->where('order_id', $order->id)->orderBy('sort_order')->get();
        $snapshot = ['version' => $order->version, 'delivery_target_date' => $order->delivery_target_date->toDateString(), 'shipment_date' => $order->shipment_date->toDateString(),
            'sizes' => $sizes->map(fn ($s) => ['id' => $s->id, 'size' => $s->size, 'qty' => (float) $s->qty, 'rate' => (float) $s->rate])->values()->all()];
        foreach ($lines as $l) {
            SsSalesOrderSize::query()->whereKey($l->size_id)->update(['qty' => $l->revised_qty, 'rate' => $l->revised_rate]);
        }
        $order->update(['delivery_target_date' => $a->revised_target_date, 'shipment_date' => $a->revised_shipment_date ?? $order->shipment_date, 'version' => $order->version + 1,
            'lead_time_days' => (int) $order->receive_date->diffInDays($a->revised_target_date)]);
        $a->update(['state' => 'APPLIED', 'applied_at' => now(), 'applied_by' => $by, 'snapshot' => $snapshot, 'version' => $order->version]);
    }

    /**
     * Every revised size is on the order and not below what has gone out (less what came back on confirmed returns).
     *
     * @param  list<array{size_id: string, revised_qty: float|int|string}>  $lines
     */
    private function checkFloor(SsSalesOrder $order, array $lines): void
    {
        $sizes = DB::table('ss_sales_order_sizes')->where('order_id', $order->id)->pluck('id')->flip();
        $fig = $this->book()->sizeFigures([$order->id]);
        $labels = $this->book()->sizeLabels(array_column($lines, 'size_id'));
        foreach ($lines as $i => $l) {
            if (! isset($sizes[$l['size_id']])) {
                throw ValidationException::withMessages(["lines.{$i}.size_id" => __('salesshipment.v.size_not_on_order', ['no' => $order->so_no])]);
            }
            $floor = max($fig[$l['size_id']]['held'] - $fig[$l['size_id']]['returned'], 0.0);
            if ((float) $l['revised_qty'] + 0.00005 < $floor) {
                throw ValidationException::withMessages(["lines.{$i}.revised_qty" => __('salesshipment.v.am_floor', ['line' => $labels[$l['size_id']] ?? '—', 'floor' => $this->num($floor)])]);
            }
        }
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
    }

    private function book(): SalesBook
    {
        return $this->book ??= new SalesBook($this->caller()->companyId);
    }

    private function find(string $no): SsAmendment
    {
        return SsAmendment::query()->where('amend_no', $no)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function sourceOf(SsSalesOrder $o): array
    {
        $sizes = DB::table('ss_sales_order_sizes')->where('order_id', $o->id)->orderBy('sort_order')->get(['id', 'qty', 'rate']);
        $labels = $this->book()->sizeLabels($sizes->pluck('id')->all());
        $fig = $this->book()->sizeFigures([$o->id]);

        return [
            'so_no' => $o->so_no, 'customer' => $this->book()->customerNames()[$o->customer_id] ?? '—', 'version' => $o->version,
            'target_date' => $o->delivery_target_date->toDateString(), 'shipment_date' => $o->shipment_date->toDateString(),
            'sizes' => $sizes->map(fn ($s) => ['size_id' => $s->id, 'label' => $labels[$s->id] ?? '—', 'qty' => (float) $s->qty, 'rate' => (float) $s->rate,
                'floor' => max(($fig[$s->id]['held'] ?? 0) - ($fig[$s->id]['returned'] ?? 0), 0.0)])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function present(SsAmendment $a): array
    {
        $b = $this->book();
        $o = SsSalesOrder::query()->findOrFail($a->order_id);
        $lines = SsAmendmentLine::query()->where('amendment_id', $a->id)->get();
        $labels = $b->sizeLabels($lines->pluck('size_id')->all());

        return [
            'amend_no' => $a->amend_no, 'so_no' => $o->so_no, 'customer' => $b->customerNames()[$o->customer_id] ?? '—', 'amend_date' => $a->amend_date->toDateString(),
            'reason' => $a->reason, 'remarks' => $a->remarks, 'prev_target_date' => $a->prev_target_date->toDateString(), 'revised_target_date' => $a->revised_target_date->toDateString(),
            'prev_shipment_date' => $a->prev_shipment_date->toDateString(), 'revised_shipment_date' => $a->revised_shipment_date?->toDateString(), 'version' => $a->version, 'state' => $a->state,
            'requested_by' => $b->user($a->created_by), 'submitted' => $b->trail($a->submitted_by, $a->submitted_at), 'approved' => $b->trail($a->approved_by, $a->approved_at),
            'applied' => $b->trail($a->applied_by, $a->applied_at), 'company' => $b->company(),
            'lines' => $lines->map(fn ($l) => ['size_id' => $l->size_id, 'label' => $labels[$l->size_id] ?? '—', 'prev_qty' => (float) $l->prev_qty, 'revised_qty' => (float) $l->revised_qty,
                'prev_rate' => (float) $l->prev_rate, 'revised_rate' => (float) $l->revised_rate])->values()->all(),
            'source' => $a->state === 'DRAFT' ? $this->sourceOf($o) : null,
        ];
    }
}
