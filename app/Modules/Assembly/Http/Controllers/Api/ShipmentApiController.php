<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Http\Controllers\Api;

use App\Modules\Assembly\Models\AsmFgLedger;
use App\Modules\Assembly\Models\AsmPackingBatch;
use App\Modules\Assembly\Models\AsmShipment;
use App\Modules\Assembly\Support\PackingBook;
use App\Modules\Assembly\Support\ReadyBook;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Assembly Floor → Shipment (AnnotechERP 20/07), as Brickland keeps it: the
 * Shipment Dashboard (key figures and every shipment, with the actions),
 * Shipment Entry (a PO / colour picked from Ready for Shipment, a shipment
 * entered full or partial, never above what is available — Ready less what
 * open shipments already hold), Mark as Shipped (confirmed shipments shipped,
 * planned ones confirmed), Shipment History; a shipment marked as shipped
 * leaves finished goods stock oldest packed batch first, with the Ready
 * balance before and after; an open shipment may be cancelled, a shipped one
 * corrected — reversed back into stock and, with a corrected quantity,
 * re-shipped at once.
 */
class ShipmentApiController extends ApiController
{
    private ?PackingBook $book = null;

    private ?ReadyBook $ready = null;

    /** The Shipment Dashboard: the key figures and the shipments for the filters. */
    public function dashboard(Request $request): JsonResponse
    {
        $this->allow('assembly.shipment.view');
        $rows = $this->ready()->rows();
        $today = PackingBook::today();
        $shipped = AsmShipment::query()->where('status', 'SHIPPED')->get();
        $active = $rows->where('ready_qty', '>', 0);

        return ApiResponse::ok([
            'kpis' => [
                'ready_for_shipment' => (int) $rows->sum('ready_qty'),
                'today_shipment' => (int) $shipped->filter(fn ($s) => PackingBook::day($s->shipped_at) === $today->toDateString())->sum('qty'),
                'total_shipped' => (int) $rows->sum('shipped_qty'), 'pending_shipment' => (int) $rows->sum('reserved_qty'),
                'partial_shipment' => $rows->where('status', 'PARTIALLY_SHIPPED')->count(), 'shipment_completed' => $rows->where('status', 'SHIPMENT_COMPLETED')->count(),
                'total_cartons' => (int) $active->sum('cartons'), 'total_buyers' => $rows->pluck('buyer')->filter()->unique()->count(),
            ],
            'shipments' => $this->list($request),
        ]);
    }

    /** Shipment History: every shipment for the filters, including POs shipped completely. */
    public function history(Request $request): JsonResponse
    {
        $this->allow('assembly.shipment.view');

        return ApiResponse::ok(['rows' => $this->list($request)]);
    }

    /** Shipment Entry (and Mark as Shipped): the PO / colours with Ready quantity, the open shipments, and the report kinds. */
    public function entry(): JsonResponse
    {
        $this->allow('assembly.shipment.view');

        return ApiResponse::ok([
            'ready' => $this->ready()->rows()->where('ready_qty', '>', 0)->map(fn ($r) => ReadyBook::public($r))->values()->all(),
            'open' => $this->shipments(AsmShipment::query()->whereIn('status', AsmShipment::OPEN)->orderBy('ship_date')->orderBy('created_at')->get()),
            'today' => PackingBook::today()->toDateString(),
            'reports' => ShipmentReportApiController::KINDS,
        ]);
    }

    /** Enter a shipment for a PO / colour: full or partial, never above what is available. */
    public function create(Request $request): JsonResponse
    {
        $this->allow('assembly.shipment.manage');
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120'], 'qty' => ['required', 'integer', 'min:1', 'max:9999999'], 'carton_qty' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'ship_date' => ['required', 'date'], 'destination' => ['nullable', 'string', 'max:120'], 'mode' => ['required', Rule::in(AsmShipment::MODES)],
            'vehicle_ref' => ['nullable', 'string', 'max:80'], 'remarks' => ['nullable', 'string', 'max:300'],
        ], ['qty.required' => __('assembly.v.sh_qty'), 'ship_date.required' => __('assembly.v.sh_date')]);

        $shipment = DB::transaction(function () use ($data) {
            $row = $this->lockedRow($data['key']);
            if ((int) $data['qty'] > $row['available_qty']) {
                throw ValidationException::withMessages(['qty' => __('assembly.v.sh_over', ['qty' => $data['qty'], 'available' => $row['available_qty']])]);
            }
            $b = AsmPackingBatch::query()->whereIn('id', $row['batch_ids'])->first();

            return AsmShipment::query()->create([
                'shipment_no' => $this->book()->nextNo('SHP', 'asm_shipments', 'shipment_no'), 'order_id' => $row['order_id'], 'po_no' => $row['po_no'], 'buyer_id' => $b->buyer_id,
                'style' => $row['style'], 'colour' => $row['colour'], 'qty' => (int) $data['qty'], 'carton_qty' => $data['carton_qty'] ?? null, 'ship_date' => $data['ship_date'],
                'destination' => $this->text($data['destination'] ?? null) ?? $row['destination'], 'mode' => $data['mode'], 'vehicle_ref' => $this->text($data['vehicle_ref'] ?? null),
                'remarks' => $this->text($data['remarks'] ?? null), 'status' => 'PLANNED', 'created_by' => $this->caller()->auditUserId(),
            ]);
        });

        return ApiResponse::created($this->ready()->shipment($shipment) + ['partial' => $shipment->qty < $this->ready()->rows()->firstWhere('key', $data['key'])['ready_qty']]);
    }

    /** Confirm a planned shipment. */
    public function confirm(string $id): JsonResponse
    {
        $this->allow('assembly.shipment.manage');
        $s = DB::transaction(function () use ($id) {
            $s = AsmShipment::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($s->status !== 'PLANNED') {
                throw ValidationException::withMessages(['id' => __('assembly.v.sh_status', ['no' => $s->shipment_no, 'status' => __('assembly.sh.status.'.$s->status)])]);
            }
            $s->update(['status' => 'CONFIRMED', 'confirmed_at' => now(), 'confirmed_by' => $this->caller()->auditUserId()]);

            return $s;
        });

        return ApiResponse::ok($this->ready()->shipment($s));
    }

    /** Mark a confirmed shipment as shipped: out of finished goods stock, oldest packed batch first; Ready goes down, Shipped goes up. */
    public function markShipped(string $id): JsonResponse
    {
        $this->allow('assembly.shipment.manage');
        $s = DB::transaction(function () use ($id) {
            $s = AsmShipment::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($s->status !== 'CONFIRMED') {
                throw ValidationException::withMessages(['id' => __('assembly.v.sh_not_confirmed', ['no' => $s->shipment_no])]);
            }
            $this->ship($s);

            return $s;
        });

        return ApiResponse::ok($this->ready()->shipment($s->refresh()));
    }

    /** Cancel an open shipment with the reason; a confirmed one is a supervisor's call. */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $this->allow('assembly.shipment.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $s = DB::transaction(function () use ($id, $data) {
            $s = AsmShipment::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if (! in_array($s->status, AsmShipment::OPEN, true)) {
                throw ValidationException::withMessages(['id' => __('assembly.v.sh_status', ['no' => $s->shipment_no, 'status' => __('assembly.sh.status.'.$s->status)])]);
            }
            if ($s->status === 'CONFIRMED') {
                $this->allow('assembly.shipment.supervise');
            }
            $s->update(['status' => 'CANCELLED', 'cancelled_at' => now(), 'cancelled_by' => $this->caller()->auditUserId(), 'cancel_reason' => trim($data['reason'])]);

            return $s;
        });

        return ApiResponse::ok($this->ready()->shipment($s));
    }

    /**
     * Correct a shipped shipment — a supervisor's call: reversed back into
     * finished goods stock and, when a corrected quantity is given, re-shipped
     * at once as a new shipment that names the one it corrects.
     */
    public function correct(Request $request, string $id): JsonResponse
    {
        $this->allow('assembly.shipment.supervise');
        $data = $request->validate(['reason' => ['required', 'string', 'max:300'], 'corrected_qty' => ['nullable', 'integer', 'min:1', 'max:9999999']]);
        [$s, $new] = DB::transaction(function () use ($id, $data) {
            $s = AsmShipment::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($s->status !== 'SHIPPED') {
                throw ValidationException::withMessages(['id' => __('assembly.v.sh_not_shipped', ['no' => $s->shipment_no])]);
            }
            $who = $this->caller()->auditUserId();
            foreach (AsmFgLedger::query()->where('doc_type', 'SHIPMENT')->where('doc_id', $s->id)->get() as $out) {
                AsmFgLedger::query()->create([
                    'order_id' => $out->order_id, 'po_no' => $out->po_no, 'buyer_id' => $out->buyer_id, 'style' => $out->style, 'colour' => $out->colour, 'size' => $out->size,
                    'batch_id' => $out->batch_id, 'doc_type' => 'SHIPMENT_REVERSAL', 'doc_no' => $s->shipment_no, 'doc_id' => $s->id, 'qty_in' => $out->qty_out, 'qty_out' => 0,
                    'posted_at' => now(), 'created_by' => $who,
                ]);
            }
            $s->update(['status' => 'REVERSED', 'reversed_at' => now(), 'reversed_by' => $who, 'reverse_reason' => trim($data['reason'])]);
            $new = null;
            if (! empty($data['corrected_qty'])) {
                $row = $this->lockedRow(ReadyBook::key($s->order_id, $s->po_no, $s->colour));
                if ((int) $data['corrected_qty'] > $row['available_qty']) {
                    throw ValidationException::withMessages(['corrected_qty' => __('assembly.v.sh_over', ['qty' => $data['corrected_qty'], 'available' => $row['available_qty']])]);
                }
                $new = AsmShipment::query()->create([
                    ...collect($s->only(['order_id', 'po_no', 'buyer_id', 'style', 'colour', 'carton_qty', 'destination', 'mode', 'vehicle_ref', 'remarks']))->all(),
                    'shipment_no' => $this->book()->nextNo('SHP', 'asm_shipments', 'shipment_no'), 'qty' => (int) $data['corrected_qty'], 'ship_date' => PackingBook::today()->toDateString(),
                    'status' => 'CONFIRMED', 'confirmed_at' => now(), 'confirmed_by' => $who, 'corrects_id' => $s->id, 'created_by' => $who,
                ]);
                $this->ship($new);
            }

            return [$s, $new];
        });

        return ApiResponse::ok(['reversed' => $this->ready()->shipment($s->refresh()), 'corrected' => $new ? $this->ready()->shipment($new->refresh()) : null]);
    }

    /**
     * Ship: the Ready balance before and after, and one stock-out line per
     * packed batch and size, oldest packed batch first.
     */
    private function ship(AsmShipment $s): void
    {
        $row = $this->lockedRow(ReadyBook::key($s->order_id, $s->po_no, $s->colour));
        if ($s->qty > $row['ready_qty']) {
            throw ValidationException::withMessages(['id' => __('assembly.v.sh_over_ready', ['no' => $s->shipment_no, 'qty' => $s->qty, 'ready' => $row['ready_qty']])]);
        }
        $left = $s->qty;
        $who = $this->caller()->auditUserId();
        $batches = AsmPackingBatch::query()->whereIn('id', $row['batch_ids'])->orderBy('packed_out_at')->orderBy('id')->get();
        $lines = AsmFgLedger::query()->whereIn('batch_id', $row['batch_ids'])->get()->groupBy('batch_id');
        foreach ($batches as $b) {
            $bySize = $lines->get($b->id, collect())->groupBy('size')->map(fn ($g) => (int) $g->sum('qty_in') - (int) $g->sum('qty_out'))
                ->sortBy(fn ($q, $size) => [PackingBook::sizeRank((string) $size), (string) $size]);
            foreach ($bySize as $size => $balance) {
                if ($left < 1) {
                    break 2;
                }
                $take = min($left, $balance);
                if ($take < 1) {
                    continue;
                }
                AsmFgLedger::query()->create([
                    'order_id' => $b->order_id, 'po_no' => $b->po_no, 'buyer_id' => $b->buyer_id, 'style' => $b->style, 'colour' => $b->colour, 'size' => (string) $size,
                    'batch_id' => $b->id, 'doc_type' => 'SHIPMENT', 'doc_no' => $s->shipment_no, 'doc_id' => $s->id, 'qty_in' => 0, 'qty_out' => $take,
                    'posted_at' => now(), 'created_by' => $who,
                ]);
                $left -= $take;
            }
        }
        if ($left > 0) {
            throw ValidationException::withMessages(['id' => __('assembly.v.sh_over_ready', ['no' => $s->shipment_no, 'qty' => $s->qty, 'ready' => $s->qty - $left])]);
        }
        $s->update(['status' => 'SHIPPED', 'shipped_at' => now(), 'shipped_by' => $who, 'ready_before' => $row['ready_qty'], 'remaining_after' => $row['ready_qty'] - $s->qty]);
    }

    /** A PO / colour, read while its batches are locked, so two desks cannot ship the same pieces. */
    private function lockedRow(string $key): array
    {
        AsmPackingBatch::query()->where('status', 'PACKED_OUT')->lockForUpdate()->get(['id']);
        $this->ready = null;

        return $this->ready()->rows()->firstWhere('key', $key) ?? throw ValidationException::withMessages(['key' => __('assembly.v.rs_unknown')]);
    }

    /** Shipments for the dashboard and history filters, newest first. */
    private function list(Request $request): array
    {
        $q = $request->validate(['q' => ['nullable', 'string', 'max:60'], 'status' => ['nullable', Rule::in(AsmShipment::STATUSES)], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        if (isset($q['from'], $q['to']) && $q['to'] < $q['from']) {
            throw ValidationException::withMessages(['to' => __('assembly.v.range_order')]);
        }
        $rows = AsmShipment::query()
            ->when($q['status'] ?? null, fn ($w, $v) => $w->where('status', $v))
            ->when($q['from'] ?? null, fn ($w, $v) => $w->whereDate('ship_date', '>=', $v))
            ->when($q['to'] ?? null, fn ($w, $v) => $w->whereDate('ship_date', '<=', $v))
            ->orderByDesc('ship_date')->orderByDesc('created_at')->orderByDesc('id')->get();
        $list = collect($this->shipments($rows));
        $needle = mb_strtolower(trim((string) ($q['q'] ?? '')));
        if ($needle !== '') {
            $list = $list->filter(fn ($r) => str_contains(mb_strtolower(implode(' ', array_filter([$r['buyer'], $r['po_no'], $r['style'], $r['order_no'], $r['colour'], $r['destination'], $r['shipment_no']]))), $needle));
        }

        return $list->values()->all();
    }

    private function shipments(Collection $rows): array
    {
        $nos = AsmShipment::query()->whereIn('id', $rows->pluck('corrects_id')->filter())->pluck('shipment_no', 'id')->all();

        return $rows->map(fn ($s) => $this->ready()->shipment($s, $nos))->values()->all();
    }

    private function text(?string $v): ?string
    {
        return $v !== null && trim($v) !== '' ? trim($v) : null;
    }

    private function book(): PackingBook
    {
        return $this->book ??= new PackingBook($this->caller()->companyId);
    }

    private function ready(): ReadyBook
    {
        return $this->ready ??= new ReadyBook($this->book());
    }
}
