<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Http\Controllers\Api;

use App\Modules\DyeHouse\Support\DyeBatches;
use App\Modules\Finishing\Models\FinBatchReceipt;
use App\Modules\Finishing\Models\FinCutRequisition;
use App\Modules\Finishing\Models\FinCutRequisitionLine;
use App\Modules\Finishing\Models\FinStoreDelivery;
use App\Modules\Finishing\Models\FinStoreDeliveryLine;
use App\Modules\Finishing\Models\FinStoreLot;
use App\Modules\Finishing\Support\FinFloor;
use App\Modules\Finishing\Support\FinStore;
use App\Shared\Fabric\FabricLength;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Finishing Line → Fabric Store (AnnotechERP 18/05), as Brickland keeps it:
 *
 * - Finished Fabric Store Receipt → a delivery note of passed batches (Ready
 *   for Delivery on the floor board), every lot of one order, carrying what
 *   the store racks it by. Saved as a draft; sent from the register when the
 *   trolley leaves (the batches are Delivered); received by the store, which
 *   is what makes each line a lot with stock.
 * - Fabric Issue to Cutting Floor → a cutting floor's requisition: what was
 *   asked and what the store released, lot by lot, the release never beyond
 *   the lot's balance (re-read here). A Partial or Complete requisition books
 *   its release out of the lots; a Draft or Cancelled one books nothing.
 */
class FinStoreApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('finishing.store.view');
        $c = $this->caller()->companyId;
        $cards = FinBatchReceipt::query()->where('status', 'ON_FLOOR')->where('stage', 'READY_FOR_DELIVERY')->orderBy('receipt_no')->get();
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        $orders = DB::table('merch_orders')->where('company_id', $c)->orderByDesc('order_no')->get(['id', 'order_no', 'style_no', 'work_style_no', 'buyer_id']);

        return ApiResponse::ok([
            'len_unit' => FabricLength::unit(),
            'companies' => [['value' => $c, 'label' => DB::table('companies')->where('id', $c)->value('name')]],
            'stores' => DB::table('stores')->where('company_id', $c)->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->all(),
            'buyers' => DB::table('buyers')->where('company_id', $c)->orderBy('name')->get(['id', 'short_name', 'name'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->short_name ?: $b->name])->all(),
            'orders' => $orders->map(fn ($o) => ['value' => $o->id, 'label' => "{$o->order_no} [".($o->work_style_no ?: $o->style_no).']', 'buyer_id' => $o->buyer_id])->all(),
            'fabric_types' => array_map(fn ($t) => ['value' => $t, 'label' => $t], FinStoreDelivery::FABRIC_TYPES),
            'cards' => $cards->map(function ($card) use ($batches) {
                $b = $batches[$card->batch_id] ?? null;

                return ['value' => $card->id, 'label' => trim(($b->batch_no ?? '—').' · '.($b->colour ?? '—').' · '.$card->receipt_no), 'batch_no' => $b?->batch_no, 'order_id' => $b?->order_id, 'colour' => $b?->colour,
                    'lot_no' => $b?->lot_no, 'dia_type' => $card->dia_type === 'BOTH' ? '' : $card->dia_type, 'qty' => (float) $card->recv_qty_kg, 'rolls' => $card->recv_rolls];
            })->values()->all(),
            'lots' => FinStore::lots(),
        ]);
    }

    // ── Store delivery / receipt ─────────────────────────────────────

    public function deliveries(): JsonResponse
    {
        $this->allow('finishing.store.view');
        $rows = FinStoreDelivery::query()->orderByDesc('receive_date')->orderByDesc('delivery_no')->get();
        $lines = FinStoreDeliveryLine::query()->whereIn('delivery_id', $rows->pluck('id'))->get()->groupBy('delivery_id');
        $stores = $this->stores();
        $orders = DB::table('merch_orders')->whereIn('id', $rows->pluck('order_id')->filter())->get(['id', 'order_no', 'style_no', 'work_style_no', 'buyer_id'])->keyBy('id');
        $buyers = DB::table('buyers')->whereIn('id', $orders->pluck('buyer_id')->filter())->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);
        $batchOf = $this->batchNos($lines->flatten()->pluck('receipt_id')->all());

        return ApiResponse::ok($rows->map(function (FinStoreDelivery $d) use ($lines, $stores, $orders, $buyers, $batchOf) {
            $mine = $lines[$d->id] ?? collect();
            $o = $orders[$d->order_id] ?? null;

            return ['id' => $d->id, 'delivery_no' => $d->delivery_no, 'receive_date' => $d->receive_date->toDateString(), 'store_id' => $d->store_id, 'store' => $stores[$d->store_id] ?? null,
                'order_id' => $d->order_id, 'order_no' => $o?->order_no, 'style' => $o ? ($o->work_style_no ?: $o->style_no) : null, 'buyer_id' => $o?->buyer_id, 'buyer' => $o ? ($buyers[$o->buyer_id] ?? null) : null,
                'batches' => $mine->map(fn ($l) => $batchOf[$l->receipt_id] ?? null)->filter()->unique()->implode(', '), 'lots' => $mine->count(),
                'grey_qty' => round((float) $mine->sum('grey_qty'), 3), 'finish_qty' => round((float) $mine->sum('finish_qty'), 3), 'finish_len' => FabricLength::fromMetres($mine->whereNotNull('finish_m')->isEmpty() ? null : $mine->sum('finish_m')),
                'len_unit' => FabricLength::unit(), 'rolls' => (int) $mine->sum('rolls'), 'status' => $d->status];
        })->all());
    }

    public function delivery(string $no): JsonResponse
    {
        $this->allow('finishing.store.view');

        return ApiResponse::ok($this->presentDelivery(FinStoreDelivery::query()->where('delivery_no', $no)->firstOrFail()));
    }

    public function saveDelivery(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('finishing.store.manage');
        $delivery = $no ? $this->deliveryOf($no, ['DRAFT']) : null;
        $data = $this->deliveryData($request, $delivery);
        $delivery = DB::transaction(function () use ($delivery, $data) {
            $head = ['receive_date' => $data['receive_date'], 'store_id' => $data['store_id'], 'order_id' => $data['order_id'], 'remarks' => $data['remarks'] ?? null];
            if ($delivery) {
                $delivery->update($head);
                $delivery->lines()->delete();
            } else {
                $delivery = FinStoreDelivery::query()->create($head + ['delivery_no' => DyeBatches::nextNumber('FFD-', 'fin_store_deliveries', 'delivery_no', $data['receive_date'], 'Ym'), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            }
            foreach ($data['lines'] as $i => $l) {
                $metres = isset($l['finish_len']) ? FabricLength::toMetres($l['finish_len']) : FabricLength::kgToMetres((float) $l['finish_qty'], $l['fin_gsm'] ?? null, $l['fin_dia'] ?? null);
                unset($l['finish_len']);
                FinStoreDeliveryLine::query()->create(['delivery_id' => $delivery->id, 'sort_order' => $i + 1, 'aop' => (bool) ($l['aop'] ?? false), 'rolls' => (int) ($l['rolls'] ?? 0), 'finish_m' => $metres] + $l);
            }

            return $delivery;
        });

        return $no ? ApiResponse::ok($this->presentDelivery($delivery->refresh())) : ApiResponse::created($this->presentDelivery($delivery->refresh()));
    }

    /** Send a draft when the trolley leaves: every batch on it is Delivered off the floor board. */
    public function sendDelivery(string $no): JsonResponse
    {
        $this->allow('finishing.store.manage');
        $delivery = $this->deliveryOf($no, ['DRAFT']);
        DB::transaction(function () use ($delivery): void {
            $cards = FinBatchReceipt::query()->whereIn('id', $delivery->lines()->pluck('receipt_id'))->lockForUpdate()->get();
            $batches = FinFloor::batches($cards->pluck('batch_id')->all());
            foreach ($cards as $card) {
                if ($card->status !== 'ON_FLOOR' || $card->stage !== 'READY_FOR_DELIVERY') {
                    throw ValidationException::withMessages(['delivery_no' => __('finishing.store.v.not_ready', ['batch' => $batches[$card->batch_id]->batch_no ?? ''])]);
                }
                FinFloor::move($card, 'DELIVERED', 'STORE', now(), null, $delivery->delivery_no, $this->caller()->auditUserId());
            }
            $delivery->update(['status' => 'SENT', 'sent_at' => now()]);
        });

        return ApiResponse::ok($this->presentDelivery($delivery->refresh()));
    }

    /** The store receives a sent note: each line becomes a lot, and only now does the stock exist. */
    public function receiveDelivery(string $no): JsonResponse
    {
        $this->allow('finishing.store.manage');
        $delivery = $this->deliveryOf($no, ['SENT']);
        DB::transaction(function () use ($delivery): void {
            $order = $delivery->order_id ? DB::table('merch_orders')->where('id', $delivery->order_id)->first(['buyer_id', 'style_no', 'work_style_no']) : null;
            $date = now('Asia/Dhaka')->toDateString();
            foreach ($delivery->lines()->get() as $l) {
                $lot = FinStoreLot::query()->create([
                    'lot_code' => DyeBatches::nextNumber('FSL-', 'fin_store_lots', 'lot_code', $date, 'Ym'), 'store_id' => $delivery->store_id, 'delivery_line_id' => $l->id, 'receipt_id' => $l->receipt_id,
                    'order_id' => $l->order_id, 'buyer_id' => $order?->buyer_id, 'style' => $order ? ($order->work_style_no ?: $order->style_no) : null, 'colour' => $l->colour, 'fabric_type' => $l->fabric_type,
                    'fabric_details' => $l->fabric_details, 'lot_no' => $l->lot_no, 'shade_no' => $l->shade_no, 'dia_type' => $l->dia_type, 'fin_gsm' => $l->fin_gsm, 'fin_dia' => $l->fin_dia,
                    'received_kg' => $l->finish_qty, 'received_m' => $l->finish_m, 'rolls' => $l->rolls, 'rate' => $l->rate, 'received_on' => $date,
                ]);
                FinStore::book($lot, 'RECEIPT', (float) $l->finish_qty, (int) $l->rolls, $date, 'DELIVERY', $delivery->id, $delivery->delivery_no, $this->caller()->auditUserId(), $l->finish_m === null ? null : (float) $l->finish_m);
            }
            $delivery->update(['status' => 'RECEIVED', 'received_at' => now(), 'received_by' => $this->caller()->auditUserId()]);
        });

        return ApiResponse::ok($this->presentDelivery($delivery->refresh()));
    }

    // ── Issue to cutting ─────────────────────────────────────────────

    public function requisitions(): JsonResponse
    {
        $this->allow('finishing.store.view');
        $rows = FinCutRequisition::query()->orderByDesc('req_date')->orderByDesc('created_at')->get();
        $lines = FinCutRequisitionLine::query()->whereIn('requisition_id', $rows->pluck('id'))->get()->groupBy('requisition_id');
        $stores = $this->stores();
        $buyers = DB::table('buyers')->whereIn('id', $rows->pluck('buyer_id')->filter())->get(['id', 'short_name', 'name'])->mapWithKeys(fn ($b) => [$b->id => $b->short_name ?: $b->name]);

        return ApiResponse::ok($rows->map(function (FinCutRequisition $r) use ($lines, $stores, $buyers) {
            $mine = $lines[$r->id] ?? collect();

            return ['id' => $r->id, 'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'cut_prog_ref' => $r->cut_prog_ref, 'store_id' => $r->store_id, 'store' => $stores[$r->store_id] ?? null,
                'buyer_id' => $r->buyer_id, 'buyer' => $buyers[$r->buyer_id] ?? null, 'style' => $r->style, 'colour' => $r->colour, 'batch_no' => $r->batch_no, 'lines' => $mine->count(),
                'req_qty' => round((float) $mine->sum('req_qty'), 3), 'issue_qty' => round((float) $mine->sum('issue_qty'), 3),
                'req_len' => FabricLength::fromMetres($mine->whereNotNull('req_m')->isEmpty() ? null : $mine->sum('req_m')), 'issue_len' => FabricLength::fromMetres($mine->whereNotNull('issue_m')->isEmpty() ? null : $mine->sum('issue_m')),
                'len_unit' => FabricLength::unit(), 'status' => $r->status];
        })->all());
    }

    public function requisition(string $no): JsonResponse
    {
        $this->allow('finishing.store.view');

        return ApiResponse::ok($this->presentRequisition(FinCutRequisition::query()->where('req_no', $no)->firstOrFail()));
    }

    public function saveRequisition(Request $request, ?string $no = null): JsonResponse
    {
        $this->allow('finishing.store.manage');
        $req = $no ? FinCutRequisition::query()->where('req_no', $no)->firstOrFail() : null;
        if ($req && in_array($req->status, ['COMPLETE', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['req_no' => __('finishing.store.v.req_closed', ['no' => $req->req_no, 'status' => __("finishing.store.opt.req_status.{$req->status}")])]);
        }
        $data = $this->requisitionData($request, $req);
        $req = DB::transaction(function () use ($req, $data) {
            $head = ['req_no' => $data['req_no'], 'req_date' => $data['req_date'], 'cut_prog_ref' => $data['cut_prog_ref'] ?? null, 'store_id' => $data['store_id'], 'buyer_id' => $data['buyer_id'] ?? null,
                'style' => $data['style'] ?? null, 'colour' => $data['colour'] ?? null, 'batch_no' => $data['batch_no'] ?? null, 'action' => $data['action'] ?? null, 'status' => $data['status'], 'remarks' => $data['remarks'] ?? null];
            if ($req) {
                $req->update($head);
                $req->lines()->delete();
                FinStore::unbook('REQUISITION', $req->id);
            } else {
                $req = FinCutRequisition::query()->create($head + ['created_by' => $this->caller()->auditUserId()]);
            }
            $books = in_array($data['status'], ['PARTIAL', 'COMPLETE'], true);
            foreach ($data['lines'] as $i => $l) {
                FinCutRequisitionLine::query()->create(['requisition_id' => $req->id, 'sort_order' => $i + 1, 'issue_qty' => (float) ($l['issue_qty'] ?? 0)] + $l);
                if ($books && ((float) ($l['issue_qty'] ?? 0) > 0 || (float) ($l['issue_m'] ?? 0) > 0)) {
                    FinStore::book(FinStoreLot::query()->findOrFail($l['lot_id']), 'ISSUE', -(float) ($l['issue_qty'] ?? 0), -(int) ($l['rolls'] ?? 0), $data['req_date'], 'REQUISITION', $req->id, $req->req_no, $this->caller()->auditUserId(),
                        isset($l['issue_m']) ? -(float) $l['issue_m'] : null);
                }
            }

            return $req;
        });

        return $no ? ApiResponse::ok($this->presentRequisition($req->refresh())) : ApiResponse::created($this->presentRequisition($req->refresh()));
    }

    /** The store's lots with what each holds now. */
    public function lots(Request $request): JsonResponse
    {
        $this->allow('finishing.store.view');

        return ApiResponse::ok(FinStore::lots($request->query('store')));
    }

    // ── Validation ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function deliveryData(Request $request, ?FinStoreDelivery $delivery): array
    {
        $own = $this->own();
        $data = $request->validate([
            'receive_date' => ['required', 'date'], 'store_id' => ['required', $own('stores')], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.receipt_id' => ['required', $own('fin_batch_receipts')], 'lines.*.order_id' => ['nullable', $own('merch_orders')], 'lines.*.fabric_type' => ['nullable', Rule::in(FinStoreDelivery::FABRIC_TYPES)],
            'lines.*.colour' => ['nullable', 'string', 'max:120'], 'lines.*.fabric_details' => ['nullable', 'string', 'max:255'], 'lines.*.item_code' => ['nullable', 'string', 'max:40'],
            'lines.*.lot_no' => ['nullable', 'string', 'max:40'], 'lines.*.tag_batch_no' => ['nullable', 'string', 'max:40'], 'lines.*.shade_no' => ['nullable', 'string', 'max:40'],
            'lines.*.dia_type' => ['nullable', Rule::in(['OPEN', 'TUBE'])], 'lines.*.fin_gsm' => ['nullable', 'numeric', 'min:0', 'max:9999'], 'lines.*.fin_dia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'lines.*.grey_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.finish_qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.rolls' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'lines.*.finish_len' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'lines.*.aop' => ['nullable', 'boolean'], 'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'receive_date.required' => __('finishing.store.v.receive_date'), 'store_id.required' => __('finishing.store.v.store'),
            'lines.required' => __('finishing.store.v.no_lots'), 'lines.min' => __('finishing.store.v.no_lots'),
        ]);
        ksort($data['lines']);
        $data['lines'] = array_values($data['lines']);
        $cards = FinBatchReceipt::query()->whereIn('id', array_column($data['lines'], 'receipt_id'))->get()->keyBy('id');
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        // A batch already on another draft or sent note is not handed over twice.
        $elsewhere = FinStoreDeliveryLine::query()->whereIn('receipt_id', $cards->keys())->whereIn('delivery_id', FinStoreDelivery::query()->whereIn('status', ['DRAFT', 'SENT'])->when($delivery, fn ($q) => $q->whereKeyNot($delivery->id))->select('id'))
            ->get(['receipt_id', 'delivery_id'])->keyBy('receipt_id');
        $notes = FinStoreDelivery::query()->whereIn('id', $elsewhere->pluck('delivery_id'))->pluck('delivery_no', 'id');
        $errors = [];
        $seen = [];
        foreach ($data['lines'] as $i => &$l) {
            $n = $i + 1;
            $card = $cards[$l['receipt_id']];
            $b = $batches[$card->batch_id] ?? null;
            $l['order_id'] = ($l['order_id'] ?? null) ?: $b?->order_id;
            if ($card->status !== 'ON_FLOOR' || $card->stage !== 'READY_FOR_DELIVERY') {
                $errors["lines.{$i}.receipt_id"] = __('finishing.store.v.row_not_ready', ['n' => $n, 'batch' => $b->batch_no ?? '']);
            } elseif (isset($elsewhere[$card->id])) {
                $errors["lines.{$i}.receipt_id"] = __('finishing.store.v.row_on_note', ['n' => $n, 'batch' => $b->batch_no ?? '', 'no' => $notes[$elsewhere[$card->id]->delivery_id] ?? '']);
            } elseif (isset($seen[$card->id])) {
                $errors["lines.{$i}.receipt_id"] = __('finishing.store.v.row_twice', ['n' => $n, 'batch' => $b->batch_no ?? '']);
            }
            $seen[$card->id] = true;
            if ((float) $l['finish_qty'] > (float) $l['grey_qty'] + 0.0005) {
                $errors["lines.{$i}.finish_qty"] = __('finishing.store.v.row_finish_over', ['n' => $n]);
            }
        }
        unset($l);
        $orders = array_values(array_unique(array_map(fn ($l) => $l['order_id'] ?? '', $data['lines'])));
        if (count($orders) > 1) {
            $errors['lines'] = __('finishing.store.v.one_order');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $data['order_id'] = $orders[0] ?: null;

        return $data;
    }

    /** @return array<string, mixed> */
    private function requisitionData(Request $request, ?FinCutRequisition $req): array
    {
        $own = $this->own();
        $data = $request->validate([
            'req_no' => ['required', 'string', 'max:40'], 'req_date' => ['required', 'date'], 'cut_prog_ref' => ['nullable', 'string', 'max:40'], 'store_id' => ['required', $own('stores')],
            'buyer_id' => ['nullable', $own('buyers')], 'style' => ['nullable', 'string', 'max:120'], 'colour' => ['nullable', 'string', 'max:120'], 'batch_no' => ['nullable', 'string', 'max:40'],
            'action' => ['nullable', 'string', 'max:120'], 'status' => ['required', Rule::in(FinCutRequisition::STATUSES)], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.lot_id' => ['required', $own('fin_store_lots')], 'lines.*.description' => ['nullable', 'string', 'max:255'], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'], 'lines.*.batch_no' => ['nullable', 'string', 'max:40'],
            'lines.*.req_qty' => ['nullable', 'required_without:lines.*.req_len', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.issue_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'lines.*.rolls' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'lines.*.req_len' => ['nullable', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.issue_len' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'req_no.required' => __('finishing.store.v.req_no'), 'req_date.required' => __('finishing.store.v.req_date'), 'store_id.required' => __('finishing.store.v.store'), 'status.required' => __('finishing.store.v.status'),
            'lines.required' => __('finishing.store.v.no_lines'), 'lines.min' => __('finishing.store.v.no_lines'),
        ]);
        ksort($data['lines']);
        $data['lines'] = array_values($data['lines']);
        $data['req_no'] = mb_strtoupper(trim($data['req_no']));
        if (FinCutRequisition::query()->where('req_no', $data['req_no'])->when($req, fn ($q) => $q->whereKeyNot($req->id))->exists()) {
            throw ValidationException::withMessages(['req_no' => __('finishing.store.v.req_twice', ['no' => $data['req_no']])]);
        }
        $lots = FinStoreLot::query()->whereIn('id', array_column($data['lines'], 'lot_id'))->get()->keyBy('id');
        $balances = FinStore::balances($lots->keys()->all(), $req?->id);
        $errors = [];
        $taking = [];
        foreach ($data['lines'] as $i => &$line) {
            // Length first (2026-11-16): the other figure from the lot's own kg per metre, else its GSM and width.
            $line = $this->lengthAndKilos($line, $lots[$line['lot_id']], $i);
        }
        unset($line);
        foreach ($data['lines'] as $i => $l) {
            $n = $i + 1;
            $lot = $lots[$l['lot_id']];
            if ($lot->store_id !== $data['store_id']) {
                $errors["lines.{$i}.lot_id"] = __('finishing.store.v.row_other_store', ['n' => $n, 'lot' => $lot->lot_code]);
                continue;
            }
            // A lot kept by length is held to its length; an older one to its kilos.
            $byLength = $lot->received_m !== null;
            $taking[$lot->id] = ($taking[$lot->id] ?? 0) + (float) ($byLength ? ($l['issue_m'] ?? 0) : ($l['issue_qty'] ?? 0));
            $left = $balances[$lot->id][$byLength ? 'm' : 'kg'] ?? 0.0;
            if ($taking[$lot->id] > $left + 0.0005) {
                $errors["lines.{$i}.".($byLength ? 'issue_len' : 'issue_qty')] = $byLength
                    ? __('finishing.store.v.row_over_length', ['n' => $n, 'lot' => $lot->lot_code, 'len' => rtrim(rtrim(number_format((float) FabricLength::fromMetres($left), 3, '.', ''), '0'), '.'), 'unit' => strtolower(FabricLength::unit())])
                    : __('finishing.store.v.row_over_balance', ['n' => $n, 'lot' => $lot->lot_code, 'kg' => rtrim(rtrim(number_format($left, 3, '.', ''), '0'), '.')]);
            }
        }
        $issued = array_sum(array_map(fn ($l) => (float) ($l['issue_qty'] ?? 0) + (float) ($l['issue_m'] ?? 0), $data['lines']));
        $short = fn ($l) => ($l['req_m'] ?? null) !== null ? (float) ($l['issue_m'] ?? 0) + 0.0005 < (float) $l['req_m'] : (float) ($l['issue_qty'] ?? 0) + 0.0005 < (float) $l['req_qty'];
        if (! $errors && $data['status'] === 'COMPLETE' && collect($data['lines'])->contains($short)) {
            $errors['status'] = __('finishing.store.v.not_complete');
        }
        if (! $errors && $data['status'] === 'PARTIAL' && $issued <= 0) {
            $errors['status'] = __('finishing.store.v.nothing_issued');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /**
     * A requisition line's length and kilos, whichever were given: lengths (in
     * the company's unit) become metres, and a missing figure is worked out from
     * the lot's own kg per metre, else from its GSM and width.
     *
     * @param  array<string, mixed>  $l
     * @return array<string, mixed>
     */
    private function lengthAndKilos(array $l, FinStoreLot $lot, int $i): array
    {
        $kgPerM = $lot->received_m ? (float) $lot->received_kg / (float) $lot->received_m : null;
        $toKg = fn (?float $m) => $m === null ? null : ($kgPerM ? round($m * $kgPerM, 3) : FabricLength::metresToKg($m, $lot->fin_gsm, $lot->fin_dia));
        $toM = fn (?float $kg) => $kg === null ? null : ($kgPerM ? round($kg / $kgPerM, 3) : FabricLength::kgToMetres($kg, $lot->fin_gsm, $lot->fin_dia));
        foreach ([['req_len', 'req_m', 'req_qty'], ['issue_len', 'issue_m', 'issue_qty']] as [$len, $m, $kg]) {
            $l[$m] = isset($l[$len]) ? FabricLength::toMetres($l[$len]) : $toM(isset($l[$kg]) ? (float) $l[$kg] : null);
            if (($l[$kg] ?? null) === null && $l[$m] !== null) {
                $l[$kg] = $toKg((float) $l[$m]);
                if ($l[$kg] === null) {
                    throw ValidationException::withMessages(["lines.{$i}.{$kg}" => __('finishing.store.v.row_kg_needed', ['n' => $i + 1, 'lot' => $lot->lot_code])]);
                }
            }
            unset($l[$len]);
        }

        return $l;
    }

    // ── Presenting ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function presentDelivery(FinStoreDelivery $d): array
    {
        $lines = $d->lines()->get();
        $cards = FinBatchReceipt::query()->whereIn('id', $lines->pluck('receipt_id'))->get()->keyBy('id');
        $batches = FinFloor::batches($cards->pluck('batch_id')->all());
        $orders = DB::table('merch_orders')->whereIn('id', $lines->pluck('order_id')->filter())->pluck('order_no', 'id');
        $num = fn ($v) => $v === null ? null : (float) $v;

        return [
            'len_unit' => FabricLength::unit(), 'id' => $d->id, 'delivery_no' => $d->delivery_no, 'receive_date' => $d->receive_date->toDateString(), 'store_id' => $d->store_id, 'order_id' => $d->order_id, 'remarks' => $d->remarks, 'status' => $d->status,
            'lines' => $lines->map(function (FinStoreDeliveryLine $l) use ($cards, $batches, $orders, $num) {
                $card = $cards[$l->receipt_id] ?? null;
                $b = $card ? ($batches[$card->batch_id] ?? null) : null;

                return ['id' => $l->id, 'receipt_id' => $l->receipt_id, 'batch' => $card ? trim(($b->batch_no ?? '—').' · '.($b->colour ?? '—').' · '.$card->receipt_no) : '—', 'order_id' => $l->order_id, 'order_no' => $orders[$l->order_id] ?? null,
                    'fabric_type' => $l->fabric_type, 'colour' => $l->colour, 'fabric_details' => $l->fabric_details, 'item_code' => $l->item_code, 'lot_no' => $l->lot_no, 'tag_batch_no' => $l->tag_batch_no, 'shade_no' => $l->shade_no,
                    'dia_type' => $l->dia_type, 'fin_gsm' => $num($l->fin_gsm), 'fin_dia' => $num($l->fin_dia), 'grey_qty' => round((float) $l->grey_qty, 3), 'finish_qty' => round((float) $l->finish_qty, 3), 'finish_len' => FabricLength::fromMetres($l->finish_m), 'rolls' => $l->rolls,
                    'rate' => $num($l->rate), 'aop' => (bool) $l->aop, 'remarks' => $l->remarks];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentRequisition(FinCutRequisition $r): array
    {
        $lines = $r->lines()->get();
        $lots = FinStoreLot::query()->whereIn('id', $lines->pluck('lot_id'))->get()->keyBy('id');
        $bal = FinStore::balances($lots->keys()->all());

        return [
            'len_unit' => FabricLength::unit(), 'id' => $r->id, 'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'cut_prog_ref' => $r->cut_prog_ref, 'store_id' => $r->store_id, 'buyer_id' => $r->buyer_id, 'style' => $r->style, 'colour' => $r->colour,
            'batch_no' => $r->batch_no, 'action' => $r->action, 'status' => $r->status, 'remarks' => $r->remarks,
            'lines' => $lines->map(fn (FinCutRequisitionLine $l) => ['id' => $l->id, 'lot_id' => $l->lot_id, 'lot' => isset($lots[$l->lot_id]) ? "{$lots[$l->lot_id]->lot_code} · ".($lots[$l->lot_id]->lot_no ?: '—').' · '.($lots[$l->lot_id]->colour ?: '—') : '—',
                'description' => $l->description, 'lot_no' => $l->lot_no, 'batch_no' => $l->batch_no, 'req_qty' => round((float) $l->req_qty, 3), 'issue_qty' => round((float) $l->issue_qty, 3), 'rolls' => $l->rolls, 'remarks' => $l->remarks,
                'req_len' => FabricLength::fromMetres($l->req_m), 'issue_len' => FabricLength::fromMetres($l->issue_m),
                'balance_kg' => $bal[$l->lot_id]['kg'] ?? 0.0, 'balance_len' => isset($lots[$l->lot_id]) && $lots[$l->lot_id]->received_m !== null ? FabricLength::fromMetres($bal[$l->lot_id]['m'] ?? 0) : null])->values()->all(),
        ];
    }

    // ── Lookups ──────────────────────────────────────────────────────

    /** @param list<string> $statuses */
    private function deliveryOf(string $no, array $statuses): FinStoreDelivery
    {
        $d = FinStoreDelivery::query()->where('delivery_no', $no)->firstOrFail();
        if (! in_array($d->status, $statuses, true)) {
            throw ValidationException::withMessages(['delivery_no' => __('finishing.store.v.delivery_locked', ['no' => $d->delivery_no, 'status' => __("finishing.store.opt.delivery_status.{$d->status}")])]);
        }

        return $d;
    }

    /** @param list<string> $cardIds @return array<string, string> */
    private function batchNos(array $cardIds): array
    {
        $cards = FinBatchReceipt::query()->whereIn('id', $cardIds)->pluck('batch_id', 'id');
        $batches = FinFloor::batches($cards->values()->all());

        return $cards->map(fn ($b) => $batches[$b]->batch_no ?? null)->filter()->all();
    }

    /** @return \Illuminate\Support\Collection<string, string> */
    private function stores(): \Illuminate\Support\Collection
    {
        return DB::table('stores')->where('company_id', $this->caller()->companyId)->get(['id', 'name', 'code'])->mapWithKeys(fn ($s) => [$s->id => "{$s->name} ({$s->code})"]);
    }

    private function own(): \Closure
    {
        $c = $this->caller()->companyId;

        return fn (string $t) => Rule::exists($t, 'id')->where('company_id', $c);
    }
}
