<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Knitting\Models\YarnPurchaseOrder;
use App\Modules\Knitting\Models\YarnPurchaseOrderLine;
use App\Modules\Knitting\Models\YarnReceipt;
use App\Modules\Knitting\Models\YarnReceiptLine;
use App\Modules\Knitting\Models\YarnReturn;
use App\Modules\Knitting\Models\YarnReturnLine;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\Knitting\Support\YarnStore;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\Buyer;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Yarn Store (AnnotechERP 15/03) → Daily Yarn Receipt and
 * its Returns & Exchanges (and the Daily Yarn Receipt Return register), as
 * Brickland works them: an MRR raised against a supplier's delivery —
 * direct, or by a placed purchase order whose open lines it takes — each
 * line what the store counted beside what the challan claimed; numbered
 * YMRR-2026120001 and posted to the yarn ledger on save. Store Receive →
 * Store Verify → Audit Verify → Accounts Approve on the register; only a
 * Store Receive is corrected or cancelled. The register's Return / Exchange
 * raises a challan of the receipt's lines (YRC-…); it is trimmed to what is
 * leaving and issued, which takes the yarn back out. A purchase order's
 * received column follows. Everything travels by number.
 */
class YarnReceiptApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $suppliers = MaterialSupplier::query()->pluck('name', 'id');

        return ApiResponse::ok(YarnProcurement::options() + [
            'placed_pos' => YarnPurchaseOrder::query()->whereIn('status', ['PLACED', 'PART_RECEIVED'])->orderByDesc('po_no')->get()
                ->map(fn (YarnPurchaseOrder $p) => ['value' => $p->po_no, 'label' => $p->po_no.' · '.($suppliers[$p->supplier_id] ?? ''), 'supplier_id' => $p->supplier_id, 'buyer_id' => $p->buyer_id, 'order_id' => $p->order_id, 'store_id' => $p->store_id])->values()->all(),
            'receipts' => YarnReceipt::query()->orderByDesc('mrr_no')->limit(300)->pluck('mrr_no')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all(),
            'returns' => YarnReturn::query()->orderByDesc('return_no')->limit(300)->pluck('return_no')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all(),
            'statuses' => YarnReceipt::STATUSES, 'recv_opts' => YarnReceipt::OPTIONS, 'store_sources' => YarnStore::SOURCES,
        ]);
    }

    /** The register: nothing filled in loads the most recent receipts; every box narrows the query. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $f = $request->validate([
            'buyer' => ['nullable', 'string'], 'order' => ['nullable', 'string'], 'po' => ['nullable', 'string', 'max:24'],
            'recv_opt' => ['nullable', Rule::in(YarnReceipt::OPTIONS)], 'status' => ['nullable', Rule::in(YarnReceipt::STATUSES)],
        ]);
        $rows = YarnReceipt::query()
            ->when(! empty($f['buyer']), fn ($q) => $q->where('buyer_id', $f['buyer']))
            ->when(! empty($f['order']), fn ($q) => $q->where('order_id', $f['order']))
            ->when(! empty($f['po']), fn ($q) => $q->whereIn('po_id', YarnPurchaseOrder::query()->where('po_no', $f['po'])->select('id')))
            ->when(! empty($f['recv_opt']), fn ($q) => $q->where('recv_opt', $f['recv_opt']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->orderByDesc('mrr_date')->orderByDesc('mrr_no')->limit(200)->get();
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id')->filter())->pluck('name', 'id');
        $buyers = Buyer::query()->whereIn('id', $rows->pluck('buyer_id')->filter())->get()->mapWithKeys(fn (Buyer $b) => [$b->id => $b->short_name ?: $b->name]);
        $pos = YarnPurchaseOrder::query()->whereIn('id', $rows->pluck('po_id')->filter())->pluck('po_no', 'id');
        $lines = YarnReceiptLine::query()->whereIn('receipt_id', $rows->pluck('id'))->get()->groupBy('receipt_id');

        return ApiResponse::ok($rows->map(fn (YarnReceipt $r) => [
            'mrr_no' => $r->mrr_no, 'mrr_date' => $r->mrr_date->toDateString(), 'store' => $stores[$r->store_id] ?? null, 'recv_opt' => $r->recv_opt,
            'po_no' => $pos[$r->po_id] ?? null, 'challan_no' => $r->challan_no, 'supplier' => $suppliers[$r->supplier_id] ?? null, 'buyer' => $buyers[$r->buyer_id] ?? null,
            'lines' => ($lines[$r->id] ?? collect())->count(), 'qty' => round((float) ($lines[$r->id] ?? collect())->sum('recv_qty'), 4),
            'challan_qty' => round((float) ($lines[$r->id] ?? collect())->sum('challan_qty'), 4),
            'value' => round((float) ($lines[$r->id] ?? collect())->sum(fn ($l) => (float) $l->recv_qty * (float) $l->rate), 2),
            'status' => $r->status, 'editable' => $r->status === 'STORE_RECEIVE',
        ])->values()->all());
    }

    /** Receive By PO: the order's lines with what it still owes, at the order's rate. */
    public function poLines(string $po): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $order = YarnPurchaseOrder::query()->where('po_no', $po)->whereIn('status', ['PLACED', 'PART_RECEIVED'])->firstOrFail();
        $names = YarnProcurement::names();

        return ApiResponse::ok([
            'po_no' => $order->po_no, 'supplier_id' => $order->supplier_id, 'buyer_id' => $order->buyer_id, 'order_id' => $order->order_id, 'store_id' => $order->store_id,
            'lines' => YarnPurchaseOrderLine::query()->where('po_id', $order->id)->orderBy('sort_order')->get()
                ->map(fn (YarnPurchaseOrderLine $l) => [
                    'po_line_id' => $l->id, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour, 'brand' => $l->brand, 'lot_no' => $l->lot_no,
                    'yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '').' '.($l->colour ?? '')),
                    'ordered' => (float) $l->qty, 'received' => (float) $l->received_qty, 'left' => round(max(0, (float) $l->qty - (float) $l->received_qty), 4), 'rate' => (float) $l->rate,
                ])->filter(fn ($l) => $l['left'] > 0)->values()->all(),
        ]);
    }

    public function show(string $receipt): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->present($this->find($receipt)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): YarnReceipt {
            $row = YarnReceipt::query()->create($this->header($data) + [
                'mrr_no' => YarnStore::nextNumber('YMRR', 'knit_yarn_receipts', 'mrr_no', $data['mrr_date']), 'status' => 'STORE_RECEIVE', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $this->writeLines($row, $data);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $receipt): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->find($receipt);
        $this->assertFrom($row, ['STORE_RECEIVE'], 'save');
        YarnStore::assertOpen($row->mrr_date->toDateString(), 'mrr_date');
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $oldPo = $row->po_id;
            $row->update($this->header($data));
            $this->writeLines($row, $data);
            if ($oldPo && $oldPo !== $row->po_id) {
                YarnStore::syncPo($oldPo);
            }
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** The register's desk checks — Store Verify, Audit Verify, Accounts Approve — and Cancel (a Store Receive only, its yarn taken back off). */
    public function transition(string $receipt, string $action): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->find($receipt);
        [$from, $to] = match ($action) {
            'verify' => [['STORE_RECEIVE'], 'STORE_VERIFY'],
            'audit' => [['STORE_VERIFY'], 'AUDIT_VERIFY'],
            'approve' => [['AUDIT_VERIFY'], 'ACCOUNTS_APPROVE'],
            'cancel' => [['STORE_RECEIVE'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertFrom($row, $from, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            if ($action === 'cancel') {
                YarnStore::assertOpen($row->mrr_date->toDateString(), 'mrr_date');
                YarnStore::unpost('RECEIPT', $row->mrr_no);
            }
            $row->update(['status' => $to]);
            YarnStore::syncPo($row->po_id);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    /** The register's Return or Exchange: a challan of every line still held, to be trimmed and issued. */
    public function raiseReturn(Request $request, string $receipt): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $row = $this->find($receipt);
        if ($row->status === 'CANCELLED') {
            throw ValidationException::withMessages(['status' => __('knitting.ystore.v.receipt_cancelled')]);
        }
        $type = $request->validate(['type' => ['required', Rule::in(['RETURN', 'EXCHANGE'])]])['type'];
        $ret = DB::transaction(function () use ($row, $type): YarnReturn {
            $ret = YarnReturn::query()->create([
                'return_no' => YarnStore::nextNumber('YRC', 'knit_yarn_returns', 'return_no', now()->toDateString()), 'receipt_id' => $row->id, 'supplier_id' => $row->supplier_id,
                'return_date' => now()->toDateString(), 'type' => $type, 'challan_type' => $type === 'EXCHANGE' ? 'RETURNABLE' : 'NON_RETURNABLE', 'finalized' => false,
                'created_by' => $this->caller()->auditUserId(),
            ]);
            foreach (YarnReceiptLine::query()->where('receipt_id', $row->id)->orderBy('sort_order')->get() as $l) {
                $left = round((float) $l->recv_qty - YarnStore::returnedOf($l->id), 4);
                if ($left > 0) {
                    YarnReturnLine::query()->create(['company_id' => $row->company_id, 'return_id' => $ret->id, 'receipt_line_id' => $l->id, 'qty' => $left]);
                }
            }

            return $ret;
        });

        return ApiResponse::created($this->presentReturn($ret));
    }

    /* ---------------- Returns & Exchanges / Daily Yarn Receipt Return ---------------- */

    /** Daily Yarn Receipt Return: the return challans, dates inclusive of both ends; blank shows the most recent. */
    public function returns(Request $request): JsonResponse
    {
        $this->allow('knitting.ystore.view');
        $f = $request->validate(['supplier' => ['nullable', 'string'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', ...($request->filled('from') ? ['after_or_equal:from'] : [])]]);
        $rows = YarnReturn::query()
            ->when(! empty($f['supplier']), fn ($q) => $q->where('supplier_id', $f['supplier']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('return_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('return_date', '<=', $f['to']))
            ->orderByDesc('return_date')->orderByDesc('return_no')->limit(200)->get();
        $receipts = YarnReceipt::query()->whereIn('id', $rows->pluck('receipt_id'))->pluck('mrr_no', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id')->filter())->pluck('name', 'id');
        $lines = YarnReturnLine::query()->whereIn('return_id', $rows->pluck('id'))->get()->groupBy('return_id');

        return ApiResponse::ok($rows->map(fn (YarnReturn $r) => [
            'return_no' => $r->return_no, 'return_date' => $r->return_date->toDateString(), 'mrr_no' => $receipts[$r->receipt_id] ?? null, 'supplier' => $suppliers[$r->supplier_id] ?? null,
            'type' => $r->type, 'challan_type' => $r->challan_type, 'lines' => ($lines[$r->id] ?? collect())->count(),
            'qty' => round((float) ($lines[$r->id] ?? collect())->sum('qty'), 4), 'finalized' => $r->finalized, 'reason' => $r->reason,
        ])->values()->all());
    }

    public function showReturn(string $return): JsonResponse
    {
        $this->allow('knitting.ystore.view');

        return ApiResponse::ok($this->presentReturn($this->findReturn($return)));
    }

    /** Trim each line to what is leaving (a line at nothing is dropped); Is Finalized issues it and takes the yarn out. */
    public function updateReturn(Request $request, string $return): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $ret = $this->findReturn($return);
        if ($ret->finalized) {
            throw ValidationException::withMessages(['finalized' => __('knitting.ystore.v.return_issued', ['no' => $ret->return_no])]);
        }
        $data = $request->validate([
            'return_date' => ['required', 'date'], 'challan_type' => ['required', Rule::in(['RETURNABLE', 'NON_RETURNABLE'])], 'finalized' => ['boolean'], 'reason' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array'], 'lines.*.receipt_line_id' => ['required', 'string'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:9999999999'], 'lines.*.remarks' => ['nullable', 'string', 'max:300'],
        ], ['return_date.required' => __('knitting.ystore.v.return_date'), 'challan_type.required' => __('knitting.ystore.v.challan_type')]);
        $receipt = YarnReceipt::query()->findOrFail($ret->receipt_id);
        $own = YarnReceiptLine::query()->where('receipt_id', $receipt->id)->get()->keyBy('id');
        $keep = [];
        $errors = [];
        foreach (YarnStore::inOrder($data['lines']) as $i => $l) {
            $line = $own->get($l['receipt_line_id']);
            $qty = (float) ($l['qty'] ?? 0);
            if (! $line) {
                $errors["lines.{$i}.receipt_line_id"] = __('knitting.ystore.v.return_line');

                continue;
            }
            if ($qty <= 0) {
                continue;
            }
            $left = round((float) $line->recv_qty - YarnStore::returnedOf($line->id), 4);
            if ($qty > $left + 0.00005) {
                $errors["lines.{$i}.qty"] = __('knitting.ystore.v.return_more', ['n' => $i + 1, 'left' => $left]);
            }
            $keep[] = ['line' => $line, 'qty' => $qty, 'remarks' => $l['remarks'] ?? null];
        }
        if (! $keep && ! $errors) {
            $errors['lines'] = __('knitting.ystore.v.return_no_lines');
        }
        if (! empty($data['finalized'])) {
            YarnStore::assertOpen($data['return_date'], 'return_date');
            foreach ($keep as $i => $k) {
                if ($k['line']->lot_id && $k['qty'] > YarnStore::balance($k['line']->lot_id) + 0.00005) {
                    $errors["lines.{$i}.qty"] = __('knitting.ystore.v.return_stock', ['n' => $i + 1, 'left' => YarnStore::balance($k['line']->lot_id)]);
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        DB::transaction(function () use ($ret, $data, $keep, $receipt): void {
            $ret->update(['return_date' => $data['return_date'], 'challan_type' => $data['challan_type'], 'reason' => $data['reason'] ?? null, 'finalized' => ! empty($data['finalized'])]);
            YarnReturnLine::query()->where('return_id', $ret->id)->delete();
            foreach ($keep as $k) {
                YarnReturnLine::query()->create(['company_id' => $ret->company_id, 'return_id' => $ret->id, 'receipt_line_id' => $k['line']->id, 'qty' => $k['qty'], 'remarks' => $k['remarks']]);
            }
            if ($ret->finalized) {
                YarnStore::post('RETURN', $ret->return_no, $ret->return_date->toDateString(), array_map(fn ($k) => [
                    'lot_id' => $k['line']->lot_id, 'qty' => -$k['qty'], 'value' => -$k['qty'] * (float) $k['line']->rate, 'doc_line_id' => $k['line']->id,
                ], array_filter($keep, fn ($k) => $k['line']->lot_id)));
                YarnStore::syncPo($receipt->po_id);
            }
        });

        return ApiResponse::ok($this->presentReturn($ret->refresh()));
    }

    public function destroyReturn(string $return): JsonResponse
    {
        $this->allow('knitting.ystore.manage');
        $ret = $this->findReturn($return);
        if ($ret->finalized) {
            throw ValidationException::withMessages(['finalized' => __('knitting.ystore.v.return_issued', ['no' => $ret->return_no])]);
        }
        $ret->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /* ---------------- helpers ---------------- */

    private function find(string $receipt): YarnReceipt
    {
        return YarnReceipt::query()->where('mrr_no', $receipt)->firstOrFail();
    }

    private function findReturn(string $return): YarnReturn
    {
        return YarnReturn::query()->where('return_no', $return)->firstOrFail();
    }

    private function assertFrom(YarnReceipt $row, array $from, string $action): void
    {
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.ystore.v.mrr_cannot', ['no' => $row->mrr_no, 'action' => __("knitting.ystore.mrr_action.{$action}"), 'status' => __("knitting.ystore.mrr_status.{$row->status}")])]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?YarnReceipt $row): array
    {
        $company = app(TenantContext::class)->companyId();
        $qty = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'mrr_date' => ['required', 'date'],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'recv_opt' => ['required', Rule::in(YarnReceipt::OPTIONS)],
            'po_no' => [$request->input('recv_opt') === 'PO' ? 'required' : 'nullable', 'string'],
            'pi_no' => [$request->input('recv_opt') === 'PI' ? 'required' : 'nullable', 'string'],
            'recv_ref' => ['nullable', 'string', 'max:64'], 'challan_no' => ['nullable', 'string', 'max:64'],
            'supplier_id' => ['nullable', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            ...YarnProcurement::lineRules('lines.*'),
            'lines.*.po_line_id' => ['nullable', 'string'], 'lines.*.lot_no' => ['nullable', 'string', 'max:40'],
            'lines.*.pack_qty' => $qty, 'lines.*.qty_per_pack' => $qty, 'lines.*.pack_uom' => ['nullable', 'string', 'max:16'],
            'lines.*.recv_qty' => $qty, 'lines.*.rate' => $qty, 'lines.*.challan_qty' => $qty, 'lines.*.cones' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ], [
            'mrr_date.required' => __('knitting.ystore.v.mrr_date'), 'store_id.required' => __('knitting.ystore.v.store'), 'recv_opt.required' => __('knitting.ystore.v.recv_opt'),
            'po_no.required' => __('knitting.ystore.v.po'), 'pi_no.required' => __('knitting.ystore.v.pi'),
            'lines.required' => __('knitting.ystore.v.mrr_no_lines'), 'lines.min' => __('knitting.ystore.v.mrr_no_lines'),
        ]);
        YarnStore::assertOpen($data['mrr_date'], 'mrr_date');
        if ($data['recv_opt'] === 'PI') {
            // Proforma invoices are raised by Commercial, which is not built yet.
            throw ValidationException::withMessages(['pi_no' => __('knitting.ystore.v.no_pi')]);
        }
        $data['po_id'] = null;
        $po = null;
        if ($data['recv_opt'] === 'PO') {
            $po = YarnPurchaseOrder::query()->where('po_no', $data['po_no'])->whereIn('status', ['PLACED', 'PART_RECEIVED'])->first();
            if (! $po) {
                throw ValidationException::withMessages(['po_no' => __('knitting.ystore.v.po')]);
            }
            $data['po_id'] = $po->id;
            $data['supplier_id'] = $po->supplier_id;
        }
        $poLines = $po ? YarnPurchaseOrderLine::query()->where('po_id', $po->id)->get()->keyBy('id') : collect();
        // What this receipt already took on each order line does not count against it.
        $mine = $row ? YarnReceiptLine::query()->where('receipt_id', $row->id)->get()->groupBy('po_line_id')->map(fn ($g) => (float) $g->sum('recv_qty')) : collect();
        $taking = [];
        $errors = [];
        foreach (YarnStore::inOrder($data['lines']) as $i => $l) {
            $n = $i + 1;
            if (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                $errors["lines.{$i}.composition_id"] = __('knitting.yarn.v.line_yarn', ['n' => $n]);
            }
            if ((float) ($l['recv_qty'] ?? 0) <= 0) {
                $errors["lines.{$i}.recv_qty"] = __('knitting.ystore.v.line_qty', ['n' => $n]);
            }
            if ($po) {
                $pl = ! empty($l['po_line_id']) ? $poLines->get($l['po_line_id']) : null;
                if (! $pl) {
                    $errors["lines.{$i}.po_line_id"] = __('knitting.ystore.v.po_line', ['n' => $n]);

                    continue;
                }
                $taking[$pl->id] = ($taking[$pl->id] ?? 0) + (float) ($l['recv_qty'] ?? 0);
                $left = (float) $pl->qty - (float) $pl->received_qty + (float) ($mine[$pl->id] ?? 0);
                if ($taking[$pl->id] > $left + 0.00005) {
                    $errors["lines.{$i}.recv_qty"] = __('knitting.ystore.v.over_po', ['n' => $n, 'left' => round($left, 4)]);
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $data['lines'] = YarnStore::inOrder($data['lines']);
        $data['source'] = $po?->source ?? 'LOCAL';

        return $data;
    }

    /** @return array<string, mixed> */
    private function header(array $data): array
    {
        return collect($data)->only(['mrr_date', 'store_id', 'recv_opt', 'po_id', 'recv_ref', 'challan_no', 'supplier_id', 'buyer_id', 'order_id', 'remarks'])->all();
    }

    /** The lines as typed, each on its stock line, posted to the ledger in place of what the receipt posted before. */
    private function writeLines(YarnReceipt $row, array $data): void
    {
        YarnReceiptLine::query()->where('receipt_id', $row->id)->delete();
        $entries = [];
        foreach ($data['lines'] as $i => $l) {
            $lot = YarnStore::lotFor([
                'store_id' => $row->store_id, 'composition_id' => $l['composition_id'] ?? null, 'yarn_count_id' => $l['yarn_count_id'] ?? null, 'colour' => $l['colour'] ?? null,
                'brand' => $l['brand'] ?? null, 'lot_no' => $l['lot_no'] ?? null, 'supplier_id' => $row->supplier_id, 'buyer_id' => $row->buyer_id, 'order_id' => $row->order_id,
                'is_buffer' => ! $row->buyer_id, 'source' => $data['source'], 'uom' => $l['uom'] ?? 'kg',
            ], $row->mrr_date->toDateString(), (float) ($l['rate'] ?? 0));
            $line = YarnReceiptLine::query()->create(collect($l)->only(['composition_id', 'yarn_count_id', 'colour', 'brand', 'lot_no', 'pack_qty', 'qty_per_pack', 'pack_uom', 'challan_qty', 'cones', 'remarks'])->all() + [
                'company_id' => $row->company_id, 'receipt_id' => $row->id, 'sort_order' => $i + 1, 'po_line_id' => $l['po_line_id'] ?? null, 'lot_id' => $lot->id,
                'recv_qty' => (float) $l['recv_qty'], 'rate' => (float) ($l['rate'] ?? 0),
            ]);
            $entries[] = ['lot_id' => $lot->id, 'qty' => (float) $line->recv_qty, 'value' => (float) $line->recv_qty * (float) $line->rate, 'doc_line_id' => $line->id];
        }
        YarnStore::post('RECEIPT', $row->mrr_no, $row->mrr_date->toDateString(), $entries);
        YarnStore::syncPo($row->po_id);
    }

    /** @return array<string, mixed> */
    private function present(YarnReceipt $row): array
    {
        $names = YarnProcurement::names();

        return [
            'mrr_no' => $row->mrr_no, 'mrr_date' => $row->mrr_date->toDateString(), 'store_id' => $row->store_id, 'recv_opt' => $row->recv_opt,
            'po_no' => $row->po_id ? YarnPurchaseOrder::query()->whereKey($row->po_id)->value('po_no') : null, 'recv_ref' => $row->recv_ref, 'challan_no' => $row->challan_no,
            'supplier_id' => $row->supplier_id, 'buyer_id' => $row->buyer_id, 'order_id' => $row->order_id, 'remarks' => $row->remarks, 'status' => $row->status,
            'editable' => $row->status === 'STORE_RECEIVE',
            'lines' => YarnReceiptLine::query()->where('receipt_id', $row->id)->orderBy('sort_order')->get()->map(fn (YarnReceiptLine $l) => [
                'id' => $l->id, 'po_line_id' => $l->po_line_id, 'composition_id' => $l->composition_id, 'yarn_count_id' => $l->yarn_count_id, 'colour' => $l->colour, 'brand' => $l->brand,
                'yarn' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '').' '.($l->colour ?? '')),
                'lot_no' => $l->lot_no, 'pack_qty' => $l->pack_qty, 'qty_per_pack' => $l->qty_per_pack, 'pack_uom' => $l->pack_uom, 'recv_qty' => (float) $l->recv_qty, 'rate' => (float) $l->rate,
                'challan_qty' => $l->challan_qty, 'cones' => $l->cones, 'remarks' => $l->remarks, 'returned' => YarnStore::returnedOf($l->id),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentReturn(YarnReturn $ret): array
    {
        $names = YarnProcurement::names();
        $receipt = YarnReceipt::query()->find($ret->receipt_id);
        $lines = YarnReturnLine::query()->where('return_id', $ret->id)->get()->keyBy('receipt_line_id');
        $all = YarnReceiptLine::query()->where('receipt_id', $ret->receipt_id)->orderBy('sort_order')->get();

        return [
            'return_no' => $ret->return_no, 'mrr_no' => $receipt?->mrr_no, 'supplier' => $ret->supplier_id ? MaterialSupplier::query()->whereKey($ret->supplier_id)->value('name') : null,
            'return_date' => $ret->return_date->toDateString(), 'type' => $ret->type, 'challan_type' => $ret->challan_type, 'finalized' => $ret->finalized, 'reason' => $ret->reason,
            // Every line of the receipt, so a trimmed-out one can be put back while the challan is open.
            'lines' => $all->map(fn (YarnReceiptLine $l) => [
                'receipt_line_id' => $l->id, 'label' => trim(($names['compositions'][$l->composition_id] ?? '').' '.($names['counts'][$l->yarn_count_id] ?? '').' '.($l->colour ?? '').($l->lot_no ? " · Lot {$l->lot_no}" : '')),
                'recv_qty' => (float) $l->recv_qty, 'left' => round((float) $l->recv_qty - YarnStore::returnedOf($l->id) + ($ret->finalized ? (float) ($lines->get($l->id)?->qty ?? 0) : 0), 4),
                'qty' => (float) ($lines->get($l->id)?->qty ?? 0), 'remarks' => $lines->get($l->id)?->remarks, 'on_challan' => $lines->has($l->id),
            ])->filter(fn ($l) => ! $ret->finalized || $l['on_challan'])->values()->all(),
        ];
    }
}
