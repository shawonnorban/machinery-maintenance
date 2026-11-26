<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Knitting\Models\YarnAdjustment;
use App\Modules\Knitting\Models\YarnAdjustmentLine;
use App\Modules\Knitting\Models\YarnIssueReturn;
use App\Modules\Knitting\Models\YarnLot;
use App\Modules\Knitting\Models\YarnMonthClose;
use App\Modules\Knitting\Models\YarnMove;
use App\Modules\Knitting\Models\YarnOpeningBalance;
use App\Modules\Knitting\Models\YarnOpeningBalanceLine;
use App\Modules\Knitting\Models\YarnPartyChallan;
use App\Modules\Knitting\Models\YarnPeriodLine;
use App\Modules\Knitting\Models\YarnRack;
use App\Modules\Knitting\Models\YarnReceipt;
use App\Modules\Knitting\Models\YarnReceiptLine;
use App\Modules\Knitting\Models\YarnReplacement;
use App\Modules\Knitting\Models\YarnReturn;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Yarn Store (AnnotechERP 15/03): Yarn Store Opening
 * Balance (declared stock, posted on Finalize), Yarn Stock Adjustment
 * (physical-count corrections), Yarn Racking Board (Rack Pending = received
 * less returned less racked; Rack Assignment puts a receipt line away), Yarn
 * Opening Entry (one store's month per lot — only Adjustment and its reason
 * are typed) and Yarn Store Month Closing (Show Exception, Month Close).
 * Documents travel by number, a store by its code, a month as 2026-12.
 */
class YarnStoreApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok(YarnProcurement::options() + [
            'store_sources' => YarnStore::SOURCES,
            'store_codes' => YarnStore::stores()->map(fn ($s) => ['value' => $s->code, 'label' => "{$s->name} ({$s->code})", 'id' => $s->id])->values()->all(),
            'obs' => YarnOpeningBalance::query()->orderByDesc('ob_no')->limit(300)->pluck('ob_no')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all(),
            'periods' => collect(range(0, 23))->map(fn ($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'))->values()->all(),
            'closed' => YarnMonthClose::query()->orderByDesc('period')->pluck('period')->all(),
        ]);
    }

    /** Stock lines holding yarn in a store (the adjustment and issue pickers). */
    public function lots(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $store = $this->storeId((string) $request->query('store', ''));

        return ApiResponse::ok(YarnStore::lots($store, ! $request->boolean('all')));
    }

    /* ---------------- Yarn Store Opening Balance ---------------- */

    public function obIndex(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $f = $request->validate([
            'store' => ['nullable', 'string'], 'buyer' => ['nullable', 'string'], 'composition' => ['nullable', 'string'], 'source' => ['nullable', Rule::in(YarnStore::SOURCES)],
            'lot' => ['nullable', 'string', 'max:40'], 'buffer' => ['nullable', Rule::in(['YES', 'NO'])], 'finalized' => ['nullable', Rule::in(['YES', 'NO'])],
        ]);
        $obs = YarnOpeningBalance::query()
            ->when(! empty($f['store']), fn ($q) => $q->where('store_id', $this->storeId($f['store'])))
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['buffer']), fn ($q) => $q->where('is_buffer', $f['buffer'] === 'YES'))
            ->when(! empty($f['finalized']), fn ($q) => $q->where('finalized', $f['finalized'] === 'YES'))
            ->pluck('id');
        // The register lists lines: the store's opening balance runs to thousands of them.
        $lines = YarnOpeningBalanceLine::query()->whereIn('ob_id', $obs)
            ->when(! empty($f['composition']), fn ($q) => $q->where('composition_id', $f['composition']))
            ->when(! empty($f['source']), fn ($q) => $q->where('source', $f['source']))
            ->when(! empty($f['lot']), fn ($q) => $q->where('lot_no', 'ilike', '%'.addcslashes($f['lot'], '%_\\').'%'))
            ->orderByDesc('created_at')->limit(300)->get();
        $heads = YarnOpeningBalance::query()->whereIn('id', $lines->pluck('ob_id'))->get()->keyBy('id');
        $stores = Store::query()->whereIn('id', $heads->pluck('store_id'))->pluck('name', 'id');
        $buyers = Buyer::query()->whereIn('id', $heads->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $names = YarnProcurement::names();

        return ApiResponse::ok($lines->map(function (YarnOpeningBalanceLine $l) use ($heads, $stores, $buyers, $names) {
            $h = $heads[$l->ob_id];

            return [
                'ob_no' => $h->ob_no, 'ob_date' => $h->ob_date->toDateString(), 'store' => $stores[$h->store_id] ?? null, 'buyer' => $buyers[$h->buyer_id] ?? null, 'is_buffer' => $h->is_buffer,
                'source' => $l->source, 'yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '').' '.($l->colour ?? '')),
                'brand' => $l->brand, 'lot_no' => $l->lot_no, 'total_qty' => (float) $l->total_qty, 'cost_price' => (float) $l->cost_price,
                'value' => round((float) $l->total_qty * (float) $l->cost_price, 2), 'finalized' => $h->finalized,
            ];
        })->values()->all());
    }

    public function obShow(string $ob): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->presentOb($this->findOb($ob)));
    }

    public function obSave(Request $request, ?string $ob = null): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $ob ? $this->findOb($ob) : null;
        if ($row?->finalized) {
            throw ValidationException::withMessages(['finalized' => __('knitting.ystore.v.ob_finalized', ['no' => $row->ob_no])]);
        }
        $company = app(TenantContext::class)->companyId();
        $qty = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'ob_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'currency_id' => ['required', Rule::exists('currencies', 'id')->where('company_id', $company)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)], 'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'is_buffer' => ['boolean'], 'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            ...YarnProcurement::lineRules('lines.*'),
            'lines.*.source' => ['required', Rule::in(YarnStore::SOURCES)], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'],
            'lines.*.supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'lines.*.pack_qty' => $qty, 'lines.*.qty_per_pack' => $qty, 'lines.*.pack_uom' => ['nullable', 'string', 'max:16'], 'lines.*.total_qty' => $qty,
            'lines.*.cost_price' => $qty, 'lines.*.cones' => ['nullable', 'integer', 'min:0', 'max:99999999'], 'lines.*.rcv_date' => ['nullable', 'date'],
        ], [
            'ob_date.required' => __('knitting.ystore.v.ob_date'), 'store_id.required' => __('knitting.ystore.v.store'), 'currency_id.required' => __('knitting.ystore.v.currency'),
            'lines.required' => __('knitting.ystore.v.ob_no_lines'), 'lines.min' => __('knitting.ystore.v.ob_no_lines'), 'lines.*.source.required' => __('knitting.ystore.v.source'),
        ]);
        $errors = [];
        $data['lines'] = YarnStore::inOrder($data['lines']);
        foreach ($data['lines'] as $i => &$l) {
            // Total Qty is Pack Qty × Qty/Pack when both are given.
            if (! empty($l['pack_qty']) && ! empty($l['qty_per_pack'])) {
                $l['total_qty'] = round((float) $l['pack_qty'] * (float) $l['qty_per_pack'], 4);
            }
            if (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                $errors["lines.{$i}.composition_id"] = __('knitting.yarn.v.line_yarn', ['n' => $i + 1]);
            }
            if ((float) ($l['total_qty'] ?? 0) <= 0) {
                $errors["lines.{$i}.total_qty"] = __('knitting.ystore.v.line_qty', ['n' => $i + 1]);
            }
        }
        unset($l);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $row = DB::transaction(function () use ($row, $data): YarnOpeningBalance {
            $head = collect($data)->only(['ob_date', 'store_id', 'currency_id', 'buyer_id', 'order_id', 'remarks'])->all() + ['is_buffer' => ! empty($data['is_buffer'])];
            $row ??= YarnOpeningBalance::query()->create($head + ['ob_no' => YarnStore::nextNumber('YOB', 'knit_yarn_obs', 'ob_no', $data['ob_date']), 'finalized' => false, 'created_by' => $this->caller()->auditUserId()]);
            $row->update($head);
            YarnOpeningBalanceLine::query()->where('ob_id', $row->id)->delete();
            foreach (YarnStore::inOrder($data['lines']) as $i => $l) {
                YarnOpeningBalanceLine::query()->create(collect($l)->only(['source', 'composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'supplier_id', 'pack_qty', 'qty_per_pack', 'pack_uom', 'cones', 'rcv_date'])->all() + [
                    'company_id' => $row->company_id, 'ob_id' => $row->id, 'sort_order' => $i + 1, 'total_qty' => (float) $l['total_qty'], 'cost_price' => (float) ($l['cost_price'] ?? 0),
                ]);
            }

            return $row;
        });

        return $ob ? ApiResponse::ok($this->presentOb($row->refresh())) : ApiResponse::created($this->presentOb($row));
    }

    public function obDestroy(string $ob): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->findOb($ob);
        if ($row->finalized) {
            throw ValidationException::withMessages(['finalized' => __('knitting.ystore.v.ob_finalized', ['no' => $row->ob_no])]);
        }
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** From the register: Finalize posts the balance to the ledger; afterwards its lines are what the store signed for. */
    public function obFinalize(string $ob): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->findOb($ob);
        if ($row->finalized) {
            throw ValidationException::withMessages(['finalized' => __('knitting.ystore.v.ob_finalized', ['no' => $row->ob_no])]);
        }
        YarnStore::assertOpen($row->ob_date->toDateString(), 'ob_date');
        DB::transaction(function () use ($row): void {
            $entries = [];
            foreach (YarnOpeningBalanceLine::query()->where('ob_id', $row->id)->orderBy('sort_order')->get() as $l) {
                $lot = YarnStore::lotFor([
                    'store_id' => $row->store_id, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour, 'brand' => $l->brand,
                    'lot_no' => $l->lot_no, 'supplier_id' => $l->supplier_id, 'buyer_id' => $row->buyer_id, 'order_id' => $row->order_id, 'is_buffer' => $row->is_buffer || ! $row->buyer_id, 'source' => $l->source,
                ], ($l->rcv_date ?? $row->ob_date)->toDateString(), (float) $l->cost_price);
                $entries[] = ['lot_id' => $lot->id, 'qty' => (float) $l->total_qty, 'value' => (float) $l->total_qty * (float) $l->cost_price, 'doc_line_id' => $l->id];
            }
            YarnStore::post('OB', $row->ob_no, $row->ob_date->toDateString(), $entries);
            $row->update(['finalized' => true]);
        });

        return ApiResponse::ok($this->presentOb($row->refresh()));
    }

    /* ---------------- Yarn Stock Adjustment ---------------- */

    public function adjIndex(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $rows = YarnAdjustment::query()->orderByDesc('adj_date')->orderByDesc('adj_no')->limit(200)->get();
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $lines = YarnAdjustmentLine::query()->whereIn('adjustment_id', $rows->pluck('id'))->get()->groupBy('adjustment_id');
        $rates = YarnLot::query()->whereIn('id', $lines->flatten()->pluck('lot_id'))->pluck('rate', 'id');
        $company = DB::table('companies')->where('id', app(TenantContext::class)->companyId())->value('name');

        return ApiResponse::ok($rows->map(fn (YarnAdjustment $a) => [
            'adj_no' => $a->adj_no, 'adj_date' => $a->adj_date->toDateString(), 'company' => $company, 'store' => $stores[$a->store_id] ?? null, 'status' => $a->status,
            'lines' => ($lines[$a->id] ?? collect())->count(), 'qty' => round((float) ($lines[$a->id] ?? collect())->sum('qty'), 4),
            'value' => round((float) ($lines[$a->id] ?? collect())->sum(fn ($l) => (float) $l->qty * (float) ($rates[$l->lot_id] ?? 0)), 2), 'reason' => $a->reason,
        ])->values()->all());
    }

    public function adjShow(string $adj): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->presentAdj(YarnAdjustment::query()->where('adj_no', $adj)->firstOrFail()));
    }

    /** A physical-count correction: each lot up (+) or down (−), never below what it holds; posted on save. */
    public function adjStore(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'adj_date' => ['required', 'date'], 'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)], 'reason' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1', 'max:200'], 'lines.*.lot_id' => ['required', 'string'], 'lines.*.qty' => ['required', 'numeric', 'not_in:0', 'min:-9999999999', 'max:9999999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], [
            'adj_date.required' => __('knitting.ystore.v.adj_date'), 'store_id.required' => __('knitting.ystore.v.store'), 'reason.required' => __('knitting.ystore.v.adj_reason'),
            'lines.required' => __('knitting.ystore.v.adj_no_lines'), 'lines.min' => __('knitting.ystore.v.adj_no_lines'), 'lines.*.qty.not_in' => __('knitting.ystore.v.adj_qty'), 'lines.*.qty.required' => __('knitting.ystore.v.adj_qty'),
        ]);
        YarnStore::assertOpen($data['adj_date'], 'adj_date');
        $errors = [];
        foreach (YarnStore::inOrder($data['lines']) as $i => $l) {
            $lot = YarnLot::query()->where('id', $l['lot_id'])->where('store_id', $data['store_id'])->first();
            if (! $lot) {
                $errors["lines.{$i}.lot_id"] = __('knitting.ystore.v.adj_lot', ['n' => $i + 1]);
            } elseif ((float) $l['qty'] < 0 && -(float) $l['qty'] > YarnStore::balance($lot->id) + 0.00005) {
                $errors["lines.{$i}.qty"] = __('knitting.ystore.v.adj_below', ['n' => $i + 1, 'left' => YarnStore::balance($lot->id)]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $row = DB::transaction(function () use ($data): YarnAdjustment {
            $row = YarnAdjustment::query()->create(collect($data)->only(['adj_date', 'store_id', 'reason'])->all() + [
                'adj_no' => YarnStore::nextNumber('YADJ', 'knit_yarn_adjustments', 'adj_no', $data['adj_date']), 'status' => 'ACTIVE', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $entries = [];
            foreach ($data['lines'] as $l) {
                $line = YarnAdjustmentLine::query()->create(['company_id' => $row->company_id, 'adjustment_id' => $row->id, 'lot_id' => $l['lot_id'], 'qty' => (float) $l['qty'], 'remarks' => $l['remarks'] ?? null]);
                $entries[] = ['lot_id' => $l['lot_id'], 'qty' => (float) $l['qty'], 'value' => (float) $l['qty'] * (float) YarnLot::query()->whereKey($l['lot_id'])->value('rate'), 'doc_line_id' => $line->id];
            }
            YarnStore::post('ADJUSTMENT', $row->adj_no, $row->adj_date->toDateString(), $entries);

            return $row;
        });

        return ApiResponse::created($this->presentAdj($row));
    }

    /** Cancel an adjustment: its correction comes back off the ledger. */
    public function adjCancel(string $adj): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = YarnAdjustment::query()->where('adj_no', $adj)->firstOrFail();
        if ($row->status === 'CANCELLED') {
            throw ValidationException::withMessages(['status' => __('knitting.ystore.v.adj_cancelled', ['no' => $row->adj_no])]);
        }
        YarnStore::assertOpen($row->adj_date->toDateString(), 'adj_date');
        DB::transaction(function () use ($row): void {
            YarnStore::unpost('ADJUSTMENT', $row->adj_no);
            $row->update(['status' => 'CANCELLED']);
        });

        return ApiResponse::ok($this->presentAdj($row->refresh()));
    }

    /* ---------------- Yarn Racking Board ---------------- */

    /** Yarn Racking Dashboard: every stock line, what came in, went back, is racked and is pending, and where it sits. */
    public function rackBoard(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $f = $request->validate([
            'store' => ['nullable', 'string'], 'buyer' => ['nullable', 'string'], 'brand' => ['nullable', 'string', 'max:80'], 'lot' => ['nullable', 'string', 'max:40'],
            'rack' => ['nullable', 'string', 'max:40'], 'buffer' => ['nullable', Rule::in(['YES', 'NO'])], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);
        $lots = YarnLot::query()
            ->when(! empty($f['store']), fn ($q) => $q->where('store_id', $this->storeId($f['store'])))
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['brand']), fn ($q) => $q->where('brand', 'ilike', '%'.addcslashes($f['brand'], '%_\\').'%'))
            ->when(! empty($f['lot']), fn ($q) => $q->where('lot_no', 'ilike', '%'.addcslashes($f['lot'], '%_\\').'%'))
            ->when(! empty($f['buffer']), fn ($q) => $q->where('is_buffer', $f['buffer'] === 'YES'))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('first_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('first_date', '<=', $f['to']))
            ->when(! empty($f['rack']), fn ($q) => $q->whereIn('id', YarnRack::query()->where('rack_no', $f['rack'])->select('lot_id')))
            ->orderBy('first_date')->limit(500)->get();
        $moves = YarnMove::query()->whereIn('lot_id', $lots->pluck('id'))->get()->groupBy('lot_id');
        $racks = YarnRack::query()->whereIn('lot_id', $lots->pluck('id'))->get()->groupBy('lot_id');
        $stores = Store::query()->whereIn('id', $lots->pluck('store_id'))->pluck('name', 'id');
        $buyers = Buyer::query()->whereIn('id', $lots->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $suppliers = MaterialSupplier::query()->whereIn('id', $lots->pluck('supplier_id')->filter())->pluck('name', 'id');
        $names = YarnProcurement::names();

        return ApiResponse::ok($lots->map(function (YarnLot $l) use ($moves, $racks, $stores, $buyers, $suppliers, $names) {
            $m = $moves[$l->id] ?? collect();
            $recv = (float) $m->whereIn('kind', ['RECEIPT', 'OB'])->sum('qty');
            $returned = 0 - (float) $m->whereIn('kind', ['RETURN', 'REPLACE'])->sum('qty');
            $racked = (float) ($racks[$l->id] ?? collect())->sum('qty');

            return YarnStore::describe($l, $names) + [
                'store' => $stores[$l->store_id] ?? null, 'buyer' => $buyers[$l->buyer_id] ?? null, 'supplier' => $suppliers[$l->supplier_id] ?? null,
                'recv' => round($recv, 4), 'returned' => round($returned, 4), 'racked' => round($racked, 4), 'pending' => round(max(0, $recv - $returned - $racked), 4),
                'balance' => round((float) $m->sum('qty'), 4),
                'racks' => ($racks[$l->id] ?? collect())->groupBy('rack_no')->map(fn ($g, $no) => ['rack_no' => $no, 'qty' => round((float) $g->sum('qty'), 4)])->values()->all(),
            ];
        })->values()->all());
    }

    /** Rack Assignment: received lines still to be put away, a receipt line at a time. */
    public function rackPending(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $f = $request->validate([
            'store' => ['required', 'string'], 'composition' => ['nullable', 'string'], 'brand' => ['nullable', 'string', 'max:80'], 'lot' => ['nullable', 'string', 'max:40'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'pending' => ['nullable', Rule::in(['ALL', 'YES', 'NO'])],
        ], ['store.required' => __('knitting.ystore.v.store_first')]);
        $receipts = YarnReceipt::query()->where('store_id', $this->storeId($f['store']))->where('status', '!=', 'CANCELLED')
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('mrr_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('mrr_date', '<=', $f['to']))->get()->keyBy('id');
        $lines = YarnReceiptLine::query()->whereIn('receipt_id', $receipts->keys())
            ->when(! empty($f['composition']), fn ($q) => $q->where('composition_id', $f['composition']))
            ->when(! empty($f['brand']), fn ($q) => $q->where('brand', 'ilike', '%'.addcslashes($f['brand'], '%_\\').'%'))
            ->when(! empty($f['lot']), fn ($q) => $q->where('lot_no', 'ilike', '%'.addcslashes($f['lot'], '%_\\').'%'))
            ->orderBy('created_at')->get();
        $racked = YarnRack::query()->whereIn('receipt_line_id', $lines->pluck('id'))->get()->groupBy('receipt_line_id');
        $names = YarnProcurement::names();
        $pending = $f['pending'] ?? 'YES';
        $rows = [];
        foreach ($lines as $l) {
            $rk = (float) ($racked[$l->id] ?? collect())->sum('qty');
            $left = round(max(0, (float) $l->recv_qty - YarnStore::returnedOf($l->id) - $rk), 4);
            if (($pending === 'YES' && $left <= 0) || ($pending === 'NO' && $left > 0)) {
                continue;
            }
            $rows[] = [
                'receipt_line_id' => $l->id, 'mrr_no' => $receipts[$l->receipt_id]->mrr_no, 'mrr_date' => $receipts[$l->receipt_id]->mrr_date->toDateString(),
                'yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '').' '.($l->colour ?? '')),
                'brand' => $l->brand, 'lot_no' => $l->lot_no, 'recv_qty' => (float) $l->recv_qty, 'returned' => YarnStore::returnedOf($l->id), 'racked' => round($rk, 4), 'pending' => $left,
                'racks' => ($racked[$l->id] ?? collect())->map(fn ($r) => "{$r->rack_no}: ".round((float) $r->qty, 4))->implode(', '),
            ];
        }

        return ApiResponse::ok($rows);
    }

    /** Rack: each row's weight goes on its rack (Rack Qty blank puts the whole pending weight away). */
    public function rackAssign(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'], 'rows.*.receipt_line_id' => ['required', 'string'], 'rows.*.rack_no' => ['required', 'string', 'max:40'],
            'rows.*.qty' => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
        ], ['rows.*.rack_no.required' => __('knitting.ystore.v.rack_no')]);
        $errors = [];
        $plan = [];
        foreach (YarnStore::inOrder($data['rows']) as $i => $r) {
            $line = YarnReceiptLine::query()->find($r['receipt_line_id']);
            if (! $line || ! $line->lot_id) {
                $errors["rows.{$i}.receipt_line_id"] = __('knitting.ystore.v.return_line');

                continue;
            }
            $left = round((float) $line->recv_qty - YarnStore::returnedOf($line->id) - (float) YarnRack::query()->where('receipt_line_id', $line->id)->sum('qty'), 4);
            $qty = isset($r['qty']) ? (float) $r['qty'] : $left;
            if ($qty <= 0 || $qty > $left + 0.00005) {
                $errors["rows.{$i}.qty"] = __('knitting.ystore.v.rack_more', ['n' => $i + 1, 'left' => max(0, $left)]);
            }
            $plan[] = [$line, strtoupper(trim($r['rack_no'])), $qty];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($plan): void {
            foreach ($plan as [$line, $rack, $qty]) {
                YarnRack::query()->create(['company_id' => $line->company_id, 'lot_id' => $line->lot_id, 'receipt_line_id' => $line->id, 'rack_no' => $rack, 'qty' => $qty, 'rack_date' => now()->toDateString(), 'created_by' => $this->caller()->auditUserId()]);
            }
        });

        return ApiResponse::ok(['count' => count($plan)]);
    }

    /* ---------------- Yarn Opening Entry ---------------- */

    /** One store, one month, per stock line: opening, in, back, adjusted, the month's adjustment and closing — only Adjustment and Reason typed. */
    public function periodGrid(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        [$storeId, $period, $start, $end] = $this->periodOf($request);

        return ApiResponse::ok(['rows' => $this->gridRows($storeId, $period, $start, $end), 'closed' => YarnMonthClose::query()->where('period', $period)->exists()]);
    }

    /** Save: each line's Adjustment and Reason of Adj, posted on the month's last day in place of what was posted before. */
    public function periodSave(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        [$storeId, $period, $start, $end] = $this->periodOf($request);
        YarnStore::assertOpen($end, 'period');
        $data = $request->validate([
            'rows' => ['present', 'array'], 'rows.*.lot_id' => ['required', 'string'], 'rows.*.adjustment' => ['nullable', 'numeric', 'min:-9999999999', 'max:9999999999'],
            'rows.*.reason' => ['nullable', 'string', 'max:255'],
        ]);
        $errors = [];
        foreach (YarnStore::inOrder($data['rows']) as $i => $r) {
            if ((float) ($r['adjustment'] ?? 0) != 0 && blank($r['reason'] ?? null)) {
                $errors["rows.{$i}.reason"] = __('knitting.ystore.v.period_reason', ['n' => $i + 1]);
            }
        }
        if (YarnPeriodLine::query()->where('store_id', $storeId)->where('period', $period)->where('finalized', true)->exists()) {
            $errors['period'] = __('knitting.ystore.v.period_finalized');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($data, $storeId, $period, $end): void {
            $entries = [];
            foreach ($data['rows'] as $r) {
                $adj = (float) ($r['adjustment'] ?? 0);
                YarnPeriodLine::query()->updateOrCreate(['store_id' => $storeId, 'period' => $period, 'lot_id' => $r['lot_id']], ['adjustment' => $adj, 'reason' => $adj != 0 ? ($r['reason'] ?? null) : null]);
                $entries[] = ['lot_id' => $r['lot_id'], 'qty' => $adj, 'value' => $adj * (float) YarnLot::query()->whereKey($r['lot_id'])->value('rate')];
            }
            YarnStore::post('PERIOD_ADJ', $this->periodDoc($storeId, $period), $end, $entries);
        });
        [, , $start] = $this->periodOf($request);

        return ApiResponse::ok(['rows' => $this->gridRows($storeId, $period, $start, $end)]);
    }

    /** Finalise All: the month's grid for the store is signed. */
    public function periodFinalize(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        [$storeId, $period, $start, $end] = $this->periodOf($request);
        YarnStore::assertOpen($end, 'period');
        DB::transaction(function () use ($storeId, $period, $start, $end): void {
            foreach ($this->gridRows($storeId, $period, $start, $end) as $r) {
                YarnPeriodLine::query()->updateOrCreate(['store_id' => $storeId, 'period' => $period, 'lot_id' => $r['lot_id']], ['finalized' => true]);
            }
        });

        return ApiResponse::ok(['rows' => $this->gridRows($storeId, $period, $start, $end)]);
    }

    /* ---------------- Yarn Store Month Closing ---------------- */

    public function closes(): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $users = DB::table('users')->pluck('name', 'id');

        return ApiResponse::ok(YarnMonthClose::query()->orderByDesc('period')->get()->map(fn (YarnMonthClose $c) => [
            'period' => $c->period, 'closed_at' => $c->closed_at->toDateTimeString(), 'closed_by' => $users[$c->closed_by] ?? null,
        ])->values()->all());
    }

    /** Show Exception: what would stop the month closing, without changing anything. */
    public function exceptions(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok(['period' => $this->period($request->query('period')), 'exceptions' => $this->exceptionsOf($this->period($request->query('period')))]);
    }

    /** Month Close: the same checks again; the month is struck if they pass. */
    public function close(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $period = $this->period($request->input('period'));
        if (YarnMonthClose::query()->where('period', $period)->exists()) {
            throw ValidationException::withMessages(['period' => __('knitting.ystore.v.already_closed')]);
        }
        $ex = $this->exceptionsOf($period);
        if ($ex) {
            throw ValidationException::withMessages(['period' => __('knitting.ystore.v.has_exceptions', ['count' => count($ex)])]);
        }
        YarnMonthClose::query()->create(['period' => $period, 'closed_at' => now(), 'closed_by' => $this->caller()->auditUserId()]);

        return ApiResponse::ok(['period' => $period]);
    }

    /* ---------------- helpers ---------------- */

    /** @return list<array{kind: string, ref: string, note: string}> */
    private function exceptionsOf(string $period): array
    {
        $start = "{$period}-01";
        $end = CarbonImmutable::parse($start)->endOfMonth()->toDateString();
        $out = [];
        if ($end >= now()->toDateString()) {
            $out[] = ['kind' => 'UNFINISHED', 'ref' => $period, 'note' => __('knitting.ystore.ex.unfinished')];
        }
        foreach (YarnReceipt::query()->where('status', 'STORE_RECEIVE')->whereBetween('mrr_date', [$start, $end])->orderBy('mrr_no')->pluck('mrr_no') as $no) {
            $out[] = ['kind' => 'MRR', 'ref' => $no, 'note' => __('knitting.ystore.ex.mrr')];
        }
        foreach (YarnOpeningBalance::query()->where('finalized', false)->whereBetween('ob_date', [$start, $end])->orderBy('ob_no')->pluck('ob_no') as $no) {
            $out[] = ['kind' => 'OB', 'ref' => $no, 'note' => __('knitting.ystore.ex.ob')];
        }
        foreach (YarnReturn::query()->where('finalized', false)->whereBetween('return_date', [$start, $end])->orderBy('return_no')->pluck('return_no') as $no) {
            $out[] = ['kind' => 'RETURN', 'ref' => $no, 'note' => __('knitting.ystore.ex.return')];
        }
        foreach (YarnIssueReturn::query()->whereIn('status', ['DRAFT', 'ISSUED'])->whereBetween('return_date', [$start, $end])->orderBy('return_no')->pluck('return_no') as $no) {
            $out[] = ['kind' => 'ISSUE_RETURN', 'ref' => $no, 'note' => __('knitting.ystore.ex.issue_return')];
        }
        foreach (YarnPartyChallan::query()->where('status', 'DRAFT')->whereBetween('challan_date', [$start, $end])->orderBy('challan_no')->pluck('challan_no') as $no) {
            $out[] = ['kind' => 'PARTY', 'ref' => $no, 'note' => __('knitting.ystore.ex.party')];
        }
        foreach (YarnReplacement::query()->where('status', 'DRAFT')->whereBetween('challan_date', [$start, $end])->orderBy('challan_no')->pluck('challan_no') as $no) {
            $out[] = ['kind' => 'REPLACE', 'ref' => $no, 'note' => __('knitting.ystore.ex.replace')];
        }
        // A store that moved yarn in the month signs its opening grid first.
        $stores = YarnMove::query()->whereBetween('move_date', [$start, $end])->distinct()->pluck('store_id');
        foreach (Store::query()->whereIn('id', $stores)->orderBy('name')->get() as $s) {
            $signed = YarnPeriodLine::query()->where('store_id', $s->id)->where('period', $period)->where('finalized', true)->exists();
            if (! $signed) {
                $out[] = ['kind' => 'GRID', 'ref' => "{$s->name} ({$s->code})", 'note' => __('knitting.ystore.ex.grid')];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function gridRows(string $storeId, string $period, string $start, string $end): array
    {
        $names = YarnProcurement::names();
        $lots = YarnLot::query()->where('store_id', $storeId)->whereDate('first_date', '<=', $end)->orderBy('first_date')->get();
        $moves = YarnMove::query()->whereIn('lot_id', $lots->pluck('id'))->whereDate('move_date', '<=', $end)->get()->groupBy('lot_id');
        $lines = YarnPeriodLine::query()->where('store_id', $storeId)->where('period', $period)->get()->keyBy('lot_id');
        $rows = [];
        foreach ($lots as $l) {
            $m = $moves[$l->id] ?? collect();
            $before = $m->filter(fn ($x) => $x->move_date->toDateString() < $start);
            $in = $m->filter(fn ($x) => $x->move_date->toDateString() >= $start);
            $opening = (float) $before->sum('qty');
            if (abs($opening) < 0.00005 && $in->isEmpty()) {
                continue;
            }
            $receive = (float) $in->whereIn('kind', ['RECEIPT', 'OB'])->sum('qty');
            $returned = 0 - (float) $in->whereIn('kind', ['RETURN', 'REPLACE'])->sum('qty');
            $adjusted = (float) $in->where('kind', 'ADJUSTMENT')->sum('qty');
            $other = (float) $in->whereNotIn('kind', ['RECEIPT', 'OB', 'RETURN', 'REPLACE', 'ADJUSTMENT', 'PERIOD_ADJ'])->sum('qty');
            $line = $lines->get($l->id);
            $rows[] = YarnStore::describe($l, $names) + [
                'opening' => round($opening, 4), 'receive' => round($receive, 4), 'returned' => round($returned, 4), 'adjusted' => round($adjusted, 4), 'issued' => round(0 - $other, 4),
                'adjustment' => (float) ($line->adjustment ?? 0), 'reason' => $line?->reason, 'closing' => round((float) $m->sum('qty'), 4), 'finalized' => (bool) ($line->finalized ?? false),
            ];
        }

        return $rows;
    }

    private function periodDoc(string $storeId, string $period): string
    {
        return 'YPA-'.Store::query()->whereKey($storeId)->value('code').'-'.$period;
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} */
    private function periodOf(Request $request): array
    {
        $code = (string) $request->query('store', $request->input('store', ''));
        if ($code === '') {
            throw ValidationException::withMessages(['store' => __('knitting.ystore.v.store_and_company')]);
        }
        $storeId = $this->storeId($code);
        $period = $this->period($request->query('period', $request->input('period')));
        $start = "{$period}-01";

        return [$storeId, $period, $start, CarbonImmutable::parse($start)->endOfMonth()->toDateString()];
    }

    private function period(mixed $period): string
    {
        if (! is_string($period) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => __('knitting.ystore.v.period')]);
        }

        return $period;
    }

    private function storeId(string $code): string
    {
        $id = Store::query()->where('code', $code)->value('id');
        if (! $id) {
            throw ValidationException::withMessages(['store' => __('knitting.ystore.v.store')]);
        }

        return $id;
    }

    private function findOb(string $ob): YarnOpeningBalance
    {
        return YarnOpeningBalance::query()->where('ob_no', $ob)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function presentOb(YarnOpeningBalance $row): array
    {
        return [
            'ob_no' => $row->ob_no, 'ob_date' => $row->ob_date->toDateString(), 'store_id' => $row->store_id, 'currency_id' => $row->currency_id, 'buyer_id' => $row->buyer_id,
            'order_id' => $row->order_id, 'is_buffer' => $row->is_buffer, 'remarks' => $row->remarks, 'finalized' => $row->finalized, 'editable' => ! $row->finalized,
            'lines' => YarnOpeningBalanceLine::query()->where('ob_id', $row->id)->orderBy('sort_order')->get()->map(fn (YarnOpeningBalanceLine $l) => [
                'source' => $l->source, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour, 'brand' => $l->brand, 'lot_no' => $l->lot_no,
                'supplier_id' => $l->supplier_id, 'pack_qty' => $l->pack_qty, 'qty_per_pack' => $l->qty_per_pack, 'pack_uom' => $l->pack_uom, 'uom' => 'kg', 'total_qty' => (float) $l->total_qty,
                'cost_price' => (float) $l->cost_price, 'cones' => $l->cones, 'rcv_date' => $l->rcv_date?->toDateString(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentAdj(YarnAdjustment $row): array
    {
        $names = YarnProcurement::names();

        return [
            'adj_no' => $row->adj_no, 'adj_date' => $row->adj_date->toDateString(), 'store_id' => $row->store_id, 'reason' => $row->reason, 'status' => $row->status,
            'lines' => YarnAdjustmentLine::query()->where('adjustment_id', $row->id)->get()->map(fn (YarnAdjustmentLine $l) => YarnStore::describe(YarnLot::query()->findOrFail($l->lot_id), $names) + [
                'qty' => (float) $l->qty, 'remarks' => $l->remarks,
            ])->values()->all(),
        ];
    }
}
