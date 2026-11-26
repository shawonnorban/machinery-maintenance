<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Knitting\Models\KnitCard;
use App\Modules\Knitting\Models\KnitGreyLot;
use App\Modules\Knitting\Models\KnitGreyReceiptRoll;
use App\Modules\Knitting\Models\KnitGreyTransfer;
use App\Modules\Knitting\Models\KnitGreyTransferLine;
use App\Modules\Knitting\Models\KnitProdOrder;
use App\Modules\Knitting\Models\KnitRoll;
use App\Modules\Knitting\Support\GreyStore;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The grey fabric movements, one paper told apart by kind, as Brickland
 * works them across Fabric Store (AnnotechERP 15/05) and Production (15/04):
 *
 * - STORE (GFT-…) Store-to-Store Grey Transfer — another store, same order.
 * - COLLAR_CUFF (CCT-…) Flat Knit Grey Fabric Transfer — flat-bed rolls off
 *   the knitting floor into a store, named as collar, cuff or both.
 * - TO_BATCH (GBT-…) Grey Store to Batch Transfer — grey rolls gathered
 *   onto a dye batch; the rolls go to the batch store.
 * - ORDER (OOT-…) Order-to-Order Grey Transfer — the same fabric, credited
 *   to another order, where it lies.
 * - REUSE (GRU-…) Grey Reuse from Leftover Store — left-over grey pulled
 *   back onto a live order.
 * - TO_LEFTOVER (GLO-…) and BATCH_TO_LEFTOVER (BLO-…) — unconsumed grey, out
 *   of the grey or the batch store into left-over.
 *
 * Saving writes a draft (Create Draft). Submit Requisition takes the fabric
 * off its stock lines (a weighed roll leaves whole); Receive Requisition
 * lands it — at the counted weight — on its new line and moves the roll;
 * Cancel before receipt puts a submitted movement back. A roll already on
 * another open movement is refused.
 */
class KnitGreyMoveApiController extends ApiController
{
    private const SLUGS = [
        'store' => 'STORE', 'collar-cuff' => 'COLLAR_CUFF', 'to-batch' => 'TO_BATCH', 'order' => 'ORDER', 'reuse' => 'REUSE',
        'to-leftover' => 'TO_LEFTOVER', 'batch-leftover' => 'BATCH_TO_LEFTOVER',
    ];

    private const PREFIX = ['STORE' => 'GFT', 'COLLAR_CUFF' => 'CCT', 'TO_BATCH' => 'GBT', 'ORDER' => 'OOT', 'REUSE' => 'GRU', 'TO_LEFTOVER' => 'GLO', 'BATCH_TO_LEFTOVER' => 'BLO'];

    /** Where a roll is once a movement of this kind is received. */
    private const MOVEMENT = ['COLLAR_CUFF' => 'GREY_STORE', 'TO_BATCH' => 'BATCH_STORE', 'REUSE' => 'GREY_STORE', 'TO_LEFTOVER' => 'LEFT_OVER', 'BATCH_TO_LEFTOVER' => 'LEFT_OVER'];

    public function index(Request $request, string $kind = 'store'): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $kind = $this->kind($kind);
        $f = $request->validate([
            'buyer' => ['nullable', 'string'], 'order' => ['nullable', 'string'], 'ref' => ['nullable', 'string'], 'status' => ['nullable', Rule::in(KnitGreyTransfer::STATUSES)],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $rows = KnitGreyTransfer::query()->where('kind', $kind)
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['order']), fn ($q) => $q->where(fn ($w) => $w->where('order_id', $f['order'])->orWhere('to_order_id', $f['order'])))
            ->when(! empty($f['ref']), fn ($q) => $q->where(fn ($w) => $w->where('trans_ref', 'ilike', '%'.$f['ref'].'%')->orWhere('transfer_no', 'ilike', '%'.$f['ref'].'%')))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('transfer_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('transfer_date', '<=', $f['to']))
            ->orderByDesc('transfer_date')->orderByDesc('transfer_no')->limit(300)->get();
        $sums = KnitGreyTransferLine::query()->whereIn('transfer_id', $rows->pluck('id'))->groupBy('transfer_id')->selectRaw('transfer_id, count(*) as n, sum(rolls) as r, sum(qty) as q')
            ->get()->keyBy('transfer_id');
        $stores = DB::table('stores')->whereIn('id', $rows->pluck('from_store_id')->merge($rows->pluck('to_store_id'))->filter()->unique())->pluck('name', 'id');
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->merge($rows->pluck('to_order_id'))->filter()->unique())->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');
        $buyers = DB::table('buyers')->whereIn('id', $rows->pluck('buyer_id')->filter()->unique())->get(['id', 'name', 'short_name'])->keyBy('id');
        $label = fn (?string $id) => isset($orders[$id]) ? trim(($orders[$id]->work_style_no ?: $orders[$id]->style_no).' · '.$orders[$id]->order_no, ' ·') : null;

        return ApiResponse::ok($rows->map(function (KnitGreyTransfer $t) use ($sums, $stores, $buyers, $label) {
            $b = $buyers[$t->buyer_id] ?? null;

            return [
                'transfer_no' => $t->transfer_no, 'kind' => $t->kind, 'transfer_date' => $t->transfer_date->toDateString(), 'trans_type' => $t->trans_type, 'cc_type' => $t->cc_type,
                'trans_ref' => $t->trans_ref, 'buyer_id' => $t->buyer_id, 'buyer' => $b ? ($b->short_name ?: $b->name) : null, 'order_id' => $t->order_id,
                'order' => $label($t->order_id), 'to_order_id' => $t->to_order_id, 'to_order' => $label($t->to_order_id), 'batch_no' => $t->batch_no,
                'from_store' => $stores[$t->from_store_id] ?? null, 'to_store' => $stores[$t->to_store_id] ?? null, 'lines' => (int) ($sums[$t->id]->n ?? 0),
                'rolls' => (int) ($sums[$t->id]->r ?? 0), 'qty' => round((float) ($sums[$t->id]->q ?? 0), 3), 'remarks' => $t->remarks, 'status' => $t->status,
            ];
        })->values()->all());
    }

    public function show(string $kind, string $no): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');

        return ApiResponse::ok($this->present($this->move($kind, $no)));
    }

    /** What a store (or an order, or a batch store) holds, for a movement's lines. */
    public function sourceLots(Request $request): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $f = $request->validate([
            'store' => ['nullable', 'string', 'required_without:order'], 'order' => ['nullable', 'string'], 'rolls' => ['nullable', 'boolean'],
            'batch' => ['nullable', Rule::in(['only', 'none'])],
        ]);
        $rows = collect(GreyStore::lots($f['store'] ?? null, true, $f['order'] ?? null))
            ->filter(fn ($l) => (empty($f['rolls']) || $l['roll_id']) && (empty($f['batch']) || ($f['batch'] === 'only' ? $l['batch_no'] : ! $l['batch_no'])))
            ->values();

        return ApiResponse::ok($rows->all());
    }

    /** Rolls still on the knitting floor (of an order), not promised to an open movement — a collar / cuff transfer's picker. */
    public function floorRolls(Request $request): JsonResponse
    {
        $this->allowEither('knitting.fstore.view', 'knitting.production.view');
        $f = $request->validate(['order' => ['required', 'string']]);
        $cards = KnitCard::query()->whereIn('prod_order_id', KnitProdOrder::query()->where('order_id', $f['order'])->pluck('id'))->get()->keyBy('id');
        $rolls = KnitRoll::query()->whereIn('card_id', $cards->keys())->where('movement', 'IN_KNITTING')->where('quality', '!=', 'REJECTED')
            ->whereNotIn('id', $this->promised())->orderBy('roll_code')->get();

        return ApiResponse::ok($rolls->map(function (KnitRoll $r) {
            $facts = GreyStore::rollFacts($r);

            return ['roll_id' => $r->id, 'roll_no' => $r->roll_code, 'fabric' => $facts['fabric'], 'colour' => $facts['colour_name'], 'colour_id' => $facts['colour_id'],
                'weight' => round((float) $r->weight - (float) $r->deducted, 3), 'label' => $r->roll_code.' · '.$facts['fabric']];
        })->values()->all());
    }

    public function save(Request $request, string $kind, ?string $no = null): JsonResponse
    {
        $this->allowEither('knitting.fstore.manage', 'knitting.production.manage');
        $row = $no ? $this->move($kind, $no) : null;
        $kind = $this->kind($kind);
        if ($row) {
            $this->assertStatus($row, ['DRAFT'], 'save');
        }
        $company = app(TenantContext::class)->companyId();
        $store = fn () => Rule::exists('stores', 'id')->where('company_id', $company);
        $order = fn () => Rule::exists('merch_orders', 'id')->where('company_id', $company);
        $need = fn (string $field) => in_array($field, $this->required($kind), true) ? 'required' : 'nullable';
        $data = $request->validate([
            'transfer_date' => ['required', 'date'], 'trans_type' => ['required', Rule::in(KnitGreyTransfer::TYPES)], 'trans_ref' => ['nullable', 'string', 'max:40'],
            'cc_type' => [$need('cc_type'), Rule::in(KnitGreyTransfer::CC_TYPES)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => [$need('order_id'), $order()], 'to_order_id' => [$need('to_order_id'), $order()],
            'from_store_id' => [$need('from_store_id'), $store()], 'to_store_id' => [$need('to_store_id'), $store()],
            'batch_no' => [$need('batch_no'), 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'], 'lines.*.lot_id' => ['nullable', 'string'], 'lines.*.roll_id' => ['nullable', 'string'],
            'lines.*.roll_no' => ['nullable', 'string', 'max:40'], 'lines.*.fabric' => ['nullable', 'string', 'max:160'],
            'lines.*.colour_id' => ['nullable', Rule::exists('colours', 'id')->where('company_id', $company)], 'lines.*.colour_name' => ['nullable', 'string', 'max:80'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'transfer_date.required' => __('knitting.fs.v.date'), 'trans_type.required' => __(in_array($kind, ['COLLAR_CUFF', 'TO_BATCH'], true) ? 'knitting.gt.v.category' : 'knitting.fs.v.trans_type'), 'cc_type.required' => __('knitting.gt.v.cc_type'),
            'order_id.required' => __($kind === 'REUSE' || $kind === 'TO_LEFTOVER' || $kind === 'BATCH_TO_LEFTOVER' ? 'knitting.gt.v.order' : 'knitting.gt.v.from_order'),
            'to_order_id.required' => __('knitting.gt.v.to_order'), 'from_store_id.required' => __('knitting.fs.v.from_store'), 'to_store_id.required' => __('knitting.fs.v.to_store'),
            'batch_no.required' => __('knitting.gt.v.batch'), 'lines.*.qty.required' => __('knitting.fs.v.line_qty'), 'lines.*.qty.gt' => __('knitting.fs.v.line_qty'),
        ]);
        if (! empty($data['from_store_id']) && ! empty($data['to_store_id']) && $data['from_store_id'] === $data['to_store_id']) {
            throw ValidationException::withMessages(['to_store_id' => __('knitting.fs.v.same_store')]);
        }
        if ($kind === 'ORDER' && $data['order_id'] === $data['to_order_id']) {
            throw ValidationException::withMessages(['to_order_id' => __('knitting.gt.v.same_order')]);
        }
        if (! empty($data['order_id']) && empty($data['buyer_id'])) {
            $data['buyer_id'] = Order::query()->whereKey($data['order_id'])->value('buyer_id');
        }
        $lines = $kind === 'COLLAR_CUFF' ? $this->rollLines($data, $row) : $this->lotLines($kind, $data, $row);
        $row = DB::transaction(function () use ($row, $data, $lines, $kind): KnitGreyTransfer {
            $head = collect($data)->only(['transfer_date', 'trans_type', 'cc_type', 'trans_ref', 'buyer_id', 'order_id', 'to_order_id', 'from_store_id', 'to_store_id', 'batch_no', 'remarks'])
                ->map(fn ($v) => $v === '' ? null : $v)->all()
                + ['cc_type' => null, 'trans_ref' => null, 'buyer_id' => null, 'order_id' => null, 'to_order_id' => null, 'from_store_id' => null, 'to_store_id' => null, 'batch_no' => null, 'remarks' => null];
            if ($head['batch_no']) {
                $head['batch_no'] = mb_strtoupper(trim($head['batch_no']));
            }
            $row ??= KnitGreyTransfer::query()->create($head + [
                'kind' => $kind, 'transfer_no' => GreyStore::nextNumber(self::PREFIX[$kind], 'knit_grey_transfers', 'transfer_no', $data['transfer_date']),
                'created_by' => $this->caller()->auditUserId(),
            ]);
            $row->update($head);
            KnitGreyTransferLine::query()->where('transfer_id', $row->id)->delete();
            foreach ($lines as $i => $l) {
                KnitGreyTransferLine::query()->create($l + ['company_id' => $row->company_id, 'transfer_id' => $row->id, 'sort_order' => $i + 1]);
            }

            return $row;
        });

        return $no ? ApiResponse::ok($this->present($row->refresh())) : ApiResponse::created($this->present($row->refresh()));
    }

    public function transition(string $kind, string $no, string $action): JsonResponse
    {
        $this->allowEither('knitting.fstore.manage', 'knitting.production.manage');
        $row = $this->move($kind, $no);
        [$from, $to] = match ($action) {
            'submit' => [['DRAFT'], 'SUBMITTED'],
            'receive' => [['SUBMITTED'], 'RECEIVED'],
            'cancel' => [['DRAFT', 'SUBMITTED'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertStatus($row, $from, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            $date = $row->transfer_date->toDateString();
            $lines = KnitGreyTransferLine::query()->where('transfer_id', $row->id)->orderBy('sort_order')->get();
            if ($action === 'submit') {
                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages(['lines' => __('knitting.fs.v.no_transfer_lines')]);
                }
                GreyStore::assertOpen($date, 'transfer_date');
                if ($row->kind !== 'COLLAR_CUFF') {
                    $entries = [];
                    foreach ($lines->groupBy('lot_id') as $lotId => $mine) {
                        $lot = KnitGreyLot::query()->findOrFail($lotId);
                        $have = GreyStore::balance($lotId);
                        $out = $lot->roll_id ? $have : (float) $mine->sum('qty');
                        if ($have + 0.00005 < (float) $mine->sum('qty') || $out <= 0) {
                            throw ValidationException::withMessages(['lines' => __('knitting.fs.v.short', ['line' => GreyStore::describe($lot)['label'], 'have' => round($have, 3)])]);
                        }
                        // A weighed roll leaves whole; what the far end counts is booked on receipt.
                        foreach ($mine as $l) {
                            $entries[] = ['lot_id' => $lotId, 'qty' => $lot->roll_id ? -$have : -(float) $l->qty, 'rolls' => -(int) $l->rolls, 'doc_line_id' => $l->id];
                        }
                    }
                    GreyStore::post('TRANSFER_OUT', $row->transfer_no, $date, $entries);
                }
            }
            if ($action === 'receive') {
                GreyStore::assertOpen($date, 'transfer_date');
                $entries = [];
                foreach ($lines as $l) {
                    $landed = $this->landing($row, $l, $date);
                    $l->update(['to_lot_id' => $landed->id]);
                    $entries[] = ['lot_id' => $landed->id, 'qty' => (float) $l->qty, 'rolls' => (int) $l->rolls, 'doc_line_id' => $l->id];
                    $rollId = $l->roll_id ?? $landed->roll_id;
                    if ($rollId && isset(self::MOVEMENT[$row->kind])) {
                        KnitRoll::query()->whereKey($rollId)->update(['movement' => self::MOVEMENT[$row->kind]]);
                    }
                }
                GreyStore::post('TRANSFER_IN', $row->transfer_no, $date, $entries);
            }
            if ($action === 'cancel' && $row->status === 'SUBMITTED') {
                GreyStore::assertOpen($date, 'transfer_date');
                GreyStore::unpost('TRANSFER_OUT', $row->transfer_no);
            }
            $row->update(['status' => $to]);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /* ---------------- Store-to-Store Grey Transfer (15/05) ---------------- */

    public function storeShow(string $no): JsonResponse
    {
        return $this->show('store', $no);
    }

    public function storeSave(Request $request, ?string $no = null): JsonResponse
    {
        return $this->save($request, 'store', $no);
    }

    public function storeTransition(string $no, string $action): JsonResponse
    {
        return $this->transition('store', $no, $action);
    }

    /* ---------------- helpers ---------------- */

    private function kind(string $slug): string
    {
        return self::SLUGS[$slug] ?? abort(404);
    }

    private function move(string $slug, string $no): KnitGreyTransfer
    {
        return KnitGreyTransfer::query()->where('kind', $this->kind($slug))->where('transfer_no', $no)->firstOrFail();
    }

    /** @return list<string> */
    private function required(string $kind): array
    {
        return match ($kind) {
            'STORE' => ['from_store_id', 'to_store_id'],
            'COLLAR_CUFF' => ['cc_type', 'order_id', 'to_store_id'],
            'TO_BATCH' => ['order_id', 'batch_no'],
            'ORDER' => ['order_id', 'to_order_id'],
            'REUSE' => ['order_id', 'from_store_id'],
            'TO_LEFTOVER', 'BATCH_TO_LEFTOVER' => ['order_id', 'from_store_id', 'to_store_id'],
        };
    }

    private function assertStatus(KnitGreyTransfer $row, array $from, string $action): void
    {
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.fs.v.cannot', [
                'no' => $row->transfer_no, 'status' => __("knitting.fs.transfer_status.{$row->status}"), 'action' => __("knitting.fs.action.{$action}"),
            ])]);
        }
    }

    /** Rolls on open (draft or submitted) movements other than this one. */
    private function promised(?string $except = null): array
    {
        $open = KnitGreyTransfer::query()->whereIn('status', ['DRAFT', 'SUBMITTED'])->when($except, fn ($q) => $q->whereKeyNot($except))->pluck('id');
        $lots = KnitGreyTransferLine::query()->whereIn('transfer_id', $open)->whereNotNull('lot_id')->pluck('lot_id');

        return KnitGreyTransferLine::query()->whereIn('transfer_id', $open)->whereNotNull('roll_id')->pluck('roll_id')
            ->merge(KnitGreyLot::query()->whereIn('id', $lots)->whereNotNull('roll_id')->pluck('roll_id'))->unique()->values()->all();
    }

    /**
     * A collar / cuff transfer's lines: rolls of the From Order still on the
     * knitting floor, each once, none promised elsewhere.
     *
     * @return list<array<string, mixed>>
     */
    private function rollLines(array $data, ?KnitGreyTransfer $row): array
    {
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __('knitting.gt.v.no_rolls')]);
        }
        $promised = $this->promised($row?->id);
        $orderCards = KnitCard::query()->whereIn('prod_order_id', KnitProdOrder::query()->where('order_id', $data['order_id'])->pluck('id'))->pluck('id')->all();
        $out = [];
        $seen = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $roll = ! empty($l['roll_id']) ? KnitRoll::query()->find($l['roll_id']) : null;
            if (! $roll) {
                throw ValidationException::withMessages(["lines.{$i}.roll_id" => __('knitting.gt.v.roll', ['n' => $n])]);
            }
            if (! in_array($roll->card_id, $orderCards, true) || $roll->movement !== 'IN_KNITTING' || $roll->quality === 'REJECTED') {
                throw ValidationException::withMessages(["lines.{$i}.roll_id" => __('knitting.gt.v.roll_floor', ['n' => $n, 'roll' => $roll->roll_code])]);
            }
            if (isset($seen[$roll->id]) || in_array($roll->id, $promised, true) || KnitGreyReceiptRoll::query()->where('roll_id', $roll->id)->exists()) {
                throw ValidationException::withMessages(["lines.{$i}.roll_id" => __('knitting.gt.v.roll_promised', ['roll' => $roll->roll_code])]);
            }
            $seen[$roll->id] = true;
            $facts = GreyStore::rollFacts($roll);
            $out[] = ['roll_id' => $roll->id, 'lot_id' => null, 'roll_no' => $roll->roll_code, 'rolls' => 1, 'qty' => round((float) $l['qty'], 4),
                'fabric' => trim((string) ($l['fabric'] ?? '')) ?: $facts['fabric'], 'colour_id' => ($l['colour_id'] ?? null) ?: null,
                'colour_name' => trim((string) ($l['colour_name'] ?? '')) ?: $facts['colour_name'], 'remarks' => ($l['remarks'] ?? '') ?: null];
        }

        return $out;
    }

    /**
     * Lines off stock lines: in the From Store (when the paper names one), of
     * the order it moves (reuse takes any order), a batch's own for batch
     * papers; a roll whole and once, never more than is there.
     *
     * @return list<array<string, mixed>>
     */
    private function lotLines(string $kind, array $data, ?KnitGreyTransfer $row): array
    {
        $lines = YarnStore::inOrder($data['lines']);
        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => __($kind === 'TO_BATCH' ? 'knitting.gt.v.no_rolls' : 'knitting.fs.v.no_transfer_lines')]);
        }
        $promised = $this->promised($row?->id);
        $out = [];
        $seen = [];
        foreach ($lines as $i => $l) {
            $n = $i + 1;
            $lot = ! empty($l['lot_id']) ? KnitGreyLot::query()->find($l['lot_id']) : null;
            if (! $lot) {
                throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.fs.v.line_lot')]);
            }
            if (! empty($data['from_store_id']) && $lot->store_id !== $data['from_store_id']) {
                throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.fs.v.line_store', ['n' => $n])]);
            }
            if ($kind !== 'REUSE' && ! empty($data['order_id']) && $lot->order_id !== $data['order_id']) {
                throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.fs.v.line_order', ['n' => $n])]);
            }
            if ($kind === 'BATCH_TO_LEFTOVER' && ! $lot->batch_no) {
                throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.gt.v.not_batch', ['n' => $n])]);
            }
            if ($kind === 'TO_BATCH' && ($lot->batch_no || ! $lot->roll_id)) {
                throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.gt.v.batch_roll', ['n' => $n])]);
            }
            if ($lot->roll_id && (isset($seen[$lot->id]) || in_array($lot->roll_id, $promised, true))) {
                throw ValidationException::withMessages(["lines.{$i}.lot_id" => __('knitting.fs.v.roll_twice', ['n' => $n])]);
            }
            $seen[$lot->id] = true;
            $have = GreyStore::balance($lot->id);
            // A roll moves whole; onto a batch its weight may be corrected to what the batch counts, never above what it holds.
            $qty = $lot->roll_id && $kind !== 'TO_BATCH' ? $have : (float) $l['qty'];
            if ($qty > $have + 0.00005) {
                throw ValidationException::withMessages(["lines.{$i}.qty" => __('knitting.fs.v.line_over', ['n' => $n, 'have' => round($have, 3)])]);
            }
            $out[] = ['lot_id' => $lot->id, 'roll_id' => null, 'roll_no' => $lot->roll_id ? null : (($l['roll_no'] ?? '') ?: null), 'rolls' => $lot->roll_id ? 1 : 0,
                'qty' => round($qty, 4), 'remarks' => ($l['remarks'] ?? '') ?: null];
        }

        return $out;
    }

    /** The stock line a received line lands on. */
    private function landing(KnitGreyTransfer $t, KnitGreyTransferLine $l, string $date): KnitGreyLot
    {
        $buyerOf = fn (?string $order) => $order ? Order::query()->whereKey($order)->value('buyer_id') : null;
        if ($t->kind === 'COLLAR_CUFF') {
            $order = $t->to_order_id ?? $t->order_id;

            return GreyStore::lotFor(array_merge(GreyStore::rollFacts(KnitRoll::query()->findOrFail($l->roll_id)), [
                'store_id' => $t->to_store_id, 'kind' => 'COLLAR_CUFF', 'order_id' => $order, 'buyer_id' => $buyerOf($order), 'fabric' => $l->fabric ?: 'Flat knit',
                'colour_id' => $l->colour_id, 'colour_name' => $l->colour_name, 'part' => __("knitting.gt.cc_type.{$t->cc_type}"),
            ]), $date);
        }
        $from = KnitGreyLot::query()->findOrFail($l->lot_id);
        $credit = fn (?string $order) => $order ? ['order_id' => $order, 'buyer_id' => $buyerOf($order)] : [];

        return match ($t->kind) {
            'STORE', 'TO_LEFTOVER' => GreyStore::lotIn($from, $t->to_store_id, $date),
            'BATCH_TO_LEFTOVER' => GreyStore::lotIn($from, $t->to_store_id, $date, ['batch_no' => null]),
            'TO_BATCH' => GreyStore::lotIn($from, $from->store_id, $date, ['batch_no' => $t->batch_no] + $credit($t->to_order_id)),
            'ORDER' => GreyStore::lotIn($from, $from->store_id, $date, $credit($t->to_order_id)),
            'REUSE' => GreyStore::lotIn($from, $from->store_id, $date, $credit($t->order_id)),
        };
    }

    /** @return array<string, mixed> */
    private function present(KnitGreyTransfer $t): array
    {
        $lines = KnitGreyTransferLine::query()->where('transfer_id', $t->id)->orderBy('sort_order')->get();
        $lots = KnitGreyLot::query()->whereIn('id', $lines->pluck('lot_id')->filter())->get()->keyBy('id');
        $names = GreyStore::names($lots->values());
        $stores = DB::table('stores')->whereIn('id', array_filter([$t->from_store_id, $t->to_store_id]))->pluck('name', 'id');
        $orders = Order::query()->whereIn('id', array_filter([$t->order_id, $t->to_order_id]))->get(['id', 'order_no', 'style_no', 'work_style_no'])->keyBy('id');
        $label = fn (?string $id) => isset($orders[$id]) ? trim(($orders[$id]->work_style_no ?: $orders[$id]->style_no).' · '.$orders[$id]->order_no, ' ·') : null;

        return [
            'id' => $t->id, 'transfer_no' => $t->transfer_no, 'kind' => $t->kind, 'transfer_date' => $t->transfer_date->toDateString(), 'trans_type' => $t->trans_type,
            'cc_type' => $t->cc_type, 'trans_ref' => $t->trans_ref, 'buyer_id' => $t->buyer_id, 'buyer' => $t->buyer_id ? DB::table('buyers')->where('id', $t->buyer_id)->value('name') : null,
            'order_id' => $t->order_id, 'order' => $label($t->order_id), 'to_order_id' => $t->to_order_id, 'to_order' => $label($t->to_order_id), 'batch_no' => $t->batch_no,
            'from_store_id' => $t->from_store_id, 'to_store_id' => $t->to_store_id, 'from_store' => $stores[$t->from_store_id] ?? null, 'to_store' => $stores[$t->to_store_id] ?? null,
            'remarks' => $t->remarks, 'status' => $t->status, 'company' => GreyStore::companyName(),
            'lines' => $lines->map(function (KnitGreyTransferLine $l) use ($lots, $names) {
                $own = ['roll_id' => $l->roll_id, 'roll_no' => $l->roll_no, 'rolls' => $l->rolls, 'qty' => (float) $l->qty, 'remarks' => $l->remarks];
                if (! $l->lot_id) {
                    return $own + ['lot_id' => null, 'fabric' => $l->fabric, 'colour' => $l->colour_name, 'colour_id' => $l->colour_id, 'order_no' => null, 'finish_dia' => null, 'gsm' => null];
                }
                $d = GreyStore::describe($lots[$l->lot_id], $names);

                return ['roll_no' => $lots[$l->lot_id]->roll_id ? $d['roll_no'] : $l->roll_no, 'balance' => GreyStore::balance($l->lot_id)] + $own + $d;
            })->values()->all(),
        ];
    }
}
