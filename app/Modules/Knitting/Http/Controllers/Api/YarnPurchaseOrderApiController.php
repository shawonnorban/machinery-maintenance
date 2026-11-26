<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\Knitting\Models\YarnComparison;
use App\Modules\Knitting\Models\YarnComparisonLine;
use App\Modules\Knitting\Models\YarnOffer;
use App\Modules\Knitting\Models\YarnPurchaseOrder;
use App\Modules\Knitting\Models\YarnPurchaseOrderLine;
use App\Modules\Knitting\Models\YarnRequisition;
use App\Modules\Knitting\Models\YarnRequisitionLine;
use App\Modules\Knitting\Support\YarnProcurement;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knit Production → Yarn Procurement (AnnotechERP 15/02) → Yarn Purchase
 * Order, as Brickland works it: an order raised off a finalized comparison —
 * picking a comparison's spinner from the list beside the form brings that
 * spinner's lines across at the price agreed — or typed straight against an
 * approved requisition. Header and lines saved together, numbered
 * YPO-2026100001; Place (from the list) puts it on the spinner, marks its
 * requisition ordered and makes it receivable at the gate, and a placed
 * order takes no more edits. Amount = quantity × rate. An order travels by
 * its number.
 */
class YarnPurchaseOrderApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('knitting.yarn.view');
        $suppliers = MaterialSupplier::query()->pluck('name', 'id');
        // Search Finalized Comparisons: one row per finalized comparison and the spinner its lines went to.
        $finalized = YarnComparison::query()->where('status', 'FINALIZED')->orderByDesc('cs_date')->get();
        $reqs = YarnRequisition::query()->whereIn('id', $finalized->pluck('requisition_id'))->pluck('requisition_no', 'id');
        $sources = [];
        foreach ($finalized as $c) {
            foreach (YarnComparisonLine::query()->where('comparison_id', $c->id)->whereNotNull('supplier_id')->get()->groupBy('supplier_id') as $supplier => $lines) {
                $qty = 0.0;
                $value = 0.0;
                foreach ($lines as $l) {
                    $q = YarnComparisonApiController::worthOrdering((float) $l->qty_local + (float) $l->qty_import, (float) $l->present_stock, (float) $l->pipeline);
                    $qty += $q;
                    $value += $q * self::agreedPrice($c->id, (int) $l->sl_no, (string) $supplier);
                }
                $sources[] = ['cs_no' => $c->cs_no, 'requisition_no' => $reqs[$c->requisition_id] ?? null, 'supplier_id' => $supplier, 'spinner' => $suppliers[$supplier] ?? null,
                    'items' => $lines->count(), 'qty' => round($qty, 4), 'value' => round($value, 2)];
            }
        }

        return ApiResponse::ok(YarnProcurement::options() + [
            'finalized' => $sources,
            'comparisons' => $finalized->map(fn ($c) => ['value' => $c->cs_no, 'label' => $c->cs_no])->values()->all(),
            'requisitions' => YarnRequisition::query()->whereIn('status', ['APPROVED', 'ORDERED'])->orderByDesc('requisition_no')->get(['requisition_no', 'buyer_id', 'order_id'])
                ->map(fn ($r) => ['value' => $r->requisition_no, 'label' => $r->requisition_no, 'buyer_id' => $r->buyer_id, 'order_id' => $r->order_id])->values()->all(),
            'orders_list' => YarnPurchaseOrder::query()->orderByDesc('po_no')->limit(300)->pluck('po_no')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all(),
            'statuses' => YarnPurchaseOrder::STATUSES,
        ]);
    }

    /** The register: nothing filled in lists the most recent orders; YR# matches every requisition the order answers. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('knitting.yarn.view');
        $f = $request->validate([
            'yr' => ['nullable', 'string', 'max:24'], 'no' => ['nullable', 'string', 'max:24'], 'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'party' => ['nullable', 'string'], 'inco' => ['nullable', Rule::in(YarnProcurement::INCO_TERMS)], 'payment' => ['nullable', Rule::in(YarnProcurement::PAYMENT_TERMS)],
            'status' => ['nullable', Rule::in(YarnPurchaseOrder::STATUSES)], 'method' => ['nullable', Rule::in(YarnProcurement::METHODS)],
        ]);
        $rows = YarnPurchaseOrder::query()
            ->when(! empty($f['yr']), fn ($q) => $q->whereIn('requisition_id', YarnRequisition::query()->where('requisition_no', 'ilike', '%'.addcslashes($f['yr'], '%_\\').'%')->select('id')))
            ->when(! empty($f['no']), fn ($q) => $q->where('po_no', 'ilike', '%'.addcslashes($f['no'], '%_\\').'%'))
            ->when(! empty($f['month']), fn ($q) => $q->whereBetween('po_date', [$f['month'].'-01', date('Y-m-t', strtotime($f['month'].'-01'))]))
            ->when(! empty($f['party']), fn ($q) => $q->where('supplier_id', $f['party']))
            ->when(! empty($f['inco']), fn ($q) => $q->where('inco_term', $f['inco']))
            ->when(! empty($f['payment']), fn ($q) => $q->where('payment_term', $f['payment']))
            ->when(! empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(! empty($f['method']), fn ($q) => $q->where('method', $f['method']))
            ->orderByDesc('po_date')->orderByDesc('po_no')->limit(200)->get();
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');
        $reqs = YarnRequisition::query()->whereIn('id', $rows->pluck('requisition_id')->filter())->pluck('requisition_no', 'id');
        $css = YarnComparison::query()->whereIn('id', $rows->pluck('comparison_id')->filter())->pluck('cs_no', 'id');
        $currencies = DB::table('currencies')->whereIn('id', $rows->pluck('currency_id'))->pluck('code', 'id');
        $lines = YarnPurchaseOrderLine::query()->whereIn('po_id', $rows->pluck('id'))->get()->groupBy('po_id');

        return ApiResponse::ok($rows->map(fn (YarnPurchaseOrder $p) => [
            'po_no' => $p->po_no, 'po_date' => $p->po_date->toDateString(), 'spinner' => $suppliers[$p->supplier_id] ?? null, 'requisition_no' => $reqs[$p->requisition_id] ?? null,
            'cs_no' => $css[$p->comparison_id] ?? null, 'source' => $p->source, 'method' => $p->method, 'inco_term' => $p->inco_term, 'payment_term' => $p->payment_term,
            'currency' => $currencies[$p->currency_id] ?? null, 'delivery_date' => $p->delivery_date?->toDateString(),
            'qty' => round((float) ($lines[$p->id] ?? collect())->sum('qty'), 4),
            'value' => round((float) ($lines[$p->id] ?? collect())->sum(fn ($l) => (float) $l->qty * (float) $l->rate), 2),
            'received' => round((float) ($lines[$p->id] ?? collect())->sum('received_qty'), 4),
            'status' => $p->status,
        ])->values()->all());
    }

    /**
     * Order This: a finalized comparison's lines that went to one spinner, at
     * the price that spinner offered on each (its cheaper band when it quoted
     * both), the quantity what is worth ordering; or, with a requisition, its
     * lines unpriced.
     */
    public function source(Request $request): JsonResponse
    {
        $this->allow('knitting.yarn.view');
        $names = YarnProcurement::names();
        if ($request->filled('cs')) {
            $cs = YarnComparison::query()->where('cs_no', (string) $request->query('cs'))->where('status', 'FINALIZED')->firstOrFail();
            $supplier = (string) $request->query('supplier', '');
            $req = YarnRequisition::query()->find($cs->requisition_id);
            $lines = YarnComparisonLine::query()->where('comparison_id', $cs->id)->where('supplier_id', $supplier)->orderBy('sl_no')->get();
            $offer = YarnOffer::query()->where('comparison_id', $cs->id)->where('supplier_id', $supplier)->orderBy('price')->first();

            return ApiResponse::ok([
                'cs_no' => $cs->cs_no, 'requisition_no' => $req?->requisition_no, 'supplier_id' => $supplier, 'buyer_id' => $req?->buyer_id, 'order_id' => $req?->order_id,
                'store_id' => $req?->store_id, 'source' => $offer?->source, 'currency_id' => $offer?->currency_id,
                'lines' => $lines->map(fn (YarnComparisonLine $l) => collect($l->only(YarnProcurement::YARN))->all() + [
                    'composition' => $names['compositions'][$l->composition_id] ?? null, 'count' => $names['counts'][$l->yarn_count_id] ?? null,
                    'knit_start_date' => $l->knit_start_date?->toDateString(), 'ship_date' => $l->ship_date?->toDateString(), 'lot_no' => null,
                    'qty' => YarnComparisonApiController::worthOrdering((float) $l->qty_local + (float) $l->qty_import, (float) $l->present_stock, (float) $l->pipeline),
                    'rate' => self::agreedPrice($cs->id, (int) $l->sl_no, $supplier),
                ])->values()->all(),
            ]);
        }
        $req = YarnRequisition::query()->where('requisition_no', (string) $request->query('requisition', ''))->whereIn('status', ['APPROVED', 'ORDERED'])->firstOrFail();

        return ApiResponse::ok([
            'cs_no' => null, 'requisition_no' => $req->requisition_no, 'supplier_id' => null, 'buyer_id' => $req->buyer_id, 'order_id' => $req->order_id, 'store_id' => $req->store_id,
            'source' => null, 'currency_id' => null,
            'lines' => YarnRequisitionLine::query()->where('requisition_id', $req->id)->orderBy('sort_order')->get()->map(fn (YarnRequisitionLine $l) => collect($l->only(YarnProcurement::YARN))->all() + [
                'composition' => $names['compositions'][$l->composition_id] ?? null, 'count' => $names['counts'][$l->yarn_count_id] ?? null,
                'knit_start_date' => $l->knit_start_date?->toDateString(), 'ship_date' => $l->ship_date?->toDateString(), 'lot_no' => null,
                'qty' => round((float) $l->qty_local + (float) $l->qty_import, 4), 'rate' => $l->booking_price !== null ? (float) $l->booking_price : null,
            ])->values()->all(),
        ]);
    }

    public function show(string $order): JsonResponse
    {
        $this->allow('knitting.yarn.view');

        return ApiResponse::ok(self::presentOrder($this->find($order)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $data = $this->validated($request);
        $row = DB::transaction(function () use ($data): YarnPurchaseOrder {
            $row = YarnPurchaseOrder::query()->create($this->header($data) + [
                'po_no' => YarnProcurement::nextNumber('YPO', 'knit_yarn_pos', 'po_no', $data['po_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            ]);
            $this->writeLines($row, $data['lines']);

            return $row;
        });

        return ApiResponse::created(self::presentOrder($row));
    }

    public function update(Request $request, string $order): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $row = $this->find($order);
        $this->assertFrom($row, ['DRAFT'], 'save');
        $data = $this->validated($request);
        DB::transaction(function () use ($row, $data): void {
            $row->update($this->header($data));
            $this->writeLines($row, $data['lines']);
        });

        return ApiResponse::ok(self::presentOrder($row->refresh()));
    }

    public function destroy(string $order): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $row = $this->find($order);
        $this->assertFrom($row, ['DRAFT'], 'delete');
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** From the list: Place (on the spinner — its requisition becomes ordered), Close, or Cancel a draft. */
    public function transition(string $order, string $action): JsonResponse
    {
        $this->allow('knitting.yarn.manage');
        $row = $this->find($order);
        [$from, $to] = match ($action) {
            'place' => [['DRAFT'], 'PLACED'],
            'close' => [['PLACED', 'PART_RECEIVED', 'RECEIVED'], 'CLOSED'],
            'cancel' => [['DRAFT'], 'CANCELLED'],
            default => abort(404),
        };
        $this->assertFrom($row, $from, $action);
        DB::transaction(function () use ($row, $action, $to): void {
            $row->update(['status' => $to] + ($action === 'place' ? ['placed_at' => now()] : []));
            if ($action === 'place' && $row->requisition_id) {
                YarnRequisition::query()->whereKey($row->requisition_id)->where('status', 'APPROVED')->update(['status' => 'ORDERED']);
            }
        });

        return ApiResponse::ok(self::presentOrder($row->refresh()));
    }

    /* ---------------- helpers ---------------- */

    /** The price a spinner offered on a comparison line: the cheaper band when it quoted both. */
    private static function agreedPrice(string $comparisonId, int $sl, string $supplierId): float
    {
        return (float) YarnOffer::query()->where('comparison_id', $comparisonId)->where('sl_no', $sl)->where('supplier_id', $supplierId)->min('price');
    }

    private function find(string $order): YarnPurchaseOrder
    {
        return YarnPurchaseOrder::query()->where('po_no', $order)->firstOrFail();
    }

    private function assertFrom(YarnPurchaseOrder $row, array $from, string $action): void
    {
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('knitting.yarn.v.po_cannot', ['no' => $row->po_no, 'action' => __("knitting.yarn.po_action.{$action}"), 'status' => __("knitting.yarn.po_status.{$row->status}")])]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'po_date' => ['required', 'date'],
            'supplier_id' => ['required', Rule::exists('mp_suppliers', 'id')->where('company_id', $company)],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'cs_no' => ['nullable', 'string'], 'requisition_no' => ['nullable', 'string'],
            'buyer_id' => ['nullable', Rule::exists('buyers', 'id')->where('company_id', $company)],
            'order_id' => ['nullable', Rule::exists('merch_orders', 'id')->where('company_id', $company)],
            'source' => ['required', Rule::in(YarnProcurement::SOURCES)],
            'method' => ['required', Rule::in(YarnProcurement::METHODS)],
            'inco_term' => ['nullable', Rule::in(YarnProcurement::INCO_TERMS)],
            'payment_term' => ['nullable', Rule::in(YarnProcurement::PAYMENT_TERMS)],
            'ship_mode' => ['nullable', Rule::in(YarnProcurement::SHIP_MODES)],
            'currency_id' => ['required', Rule::exists('currencies', 'id')->where('company_id', $company)],
            'conv_rate' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'delivery_date' => ['nullable', 'date'], 'shipment_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            ...YarnProcurement::lineRules('lines.*'),
            'lines.*.lot_no' => ['nullable', 'string', 'max:40'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], [
            'po_date.required' => __('knitting.yarn.v.po_date'), 'supplier_id.required' => __('knitting.yarn.v.po_supplier'), 'store_id.required' => __('knitting.yarn.v.store'),
            'source.required' => __('knitting.yarn.v.po_source'), 'method.required' => __('knitting.yarn.v.po_method'), 'currency_id.required' => __('knitting.yarn.v.po_currency'),
            'lines.required' => __('knitting.yarn.v.po_no_lines'), 'lines.min' => __('knitting.yarn.v.po_no_lines'),
        ]);
        $data['comparison_id'] = null;
        if (! empty($data['cs_no'])) {
            $cs = YarnComparison::query()->where('cs_no', $data['cs_no'])->first();
            if (! $cs || $cs->status !== 'FINALIZED') {
                throw ValidationException::withMessages(['cs_no' => __('knitting.yarn.v.po_cs')]);
            }
            $data['comparison_id'] = $cs->id;
            $data['requisition_no'] ??= YarnRequisition::query()->whereKey($cs->requisition_id)->value('requisition_no');
        }
        $data['requisition_id'] = null;
        if (! empty($data['requisition_no'])) {
            $req = YarnRequisition::query()->where('requisition_no', $data['requisition_no'])->first();
            if (! $req || ! in_array($req->status, ['APPROVED', 'ORDERED'], true)) {
                throw ValidationException::withMessages(['requisition_no' => __('knitting.yarn.v.po_requisition')]);
            }
            $data['requisition_id'] = $req->id;
        }
        $errors = [];
        foreach (array_values($data['lines']) as $i => $l) {
            if (empty($l['composition_id']) && empty($l['yarn_count_id'])) {
                $errors["lines.{$i}.composition_id"] = __('knitting.yarn.v.line_yarn', ['n' => $i + 1]);
            }
            if (empty($l['uom'])) {
                $errors["lines.{$i}.uom"] = __('knitting.yarn.v.line_uom', ['n' => $i + 1]);
            }
            if ((float) ($l['qty'] ?? 0) <= 0) {
                $errors["lines.{$i}.qty"] = __('knitting.yarn.v.po_line_qty', ['n' => $i + 1]);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $data['lines'] = array_values($data['lines']);

        return $data;
    }

    /** @return array<string, mixed> */
    private function header(array $data): array
    {
        return collect($data)->only(['po_date', 'supplier_id', 'store_id', 'comparison_id', 'requisition_id', 'buyer_id', 'order_id', 'source', 'method', 'inco_term', 'payment_term', 'ship_mode', 'currency_id', 'conv_rate', 'delivery_date', 'shipment_date', 'remarks'])->all();
    }

    private function writeLines(YarnPurchaseOrder $row, array $lines): void
    {
        YarnPurchaseOrderLine::query()->where('po_id', $row->id)->delete();
        foreach ($lines as $i => $l) {
            YarnPurchaseOrderLine::query()->create(collect($l)->only([...YarnProcurement::YARN, 'lot_no'])->all() + [
                'company_id' => $row->company_id, 'po_id' => $row->id, 'sort_order' => $i + 1, 'uom' => UomCodes::normalize($l['uom']),
                'qty' => (float) $l['qty'], 'rate' => (float) ($l['rate'] ?? 0),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public static function presentOrder(YarnPurchaseOrder $row): array
    {
        $names = YarnProcurement::names();
        $lines = YarnPurchaseOrderLine::query()->where('po_id', $row->id)->orderBy('sort_order')->get();

        return [
            'po_no' => $row->po_no, 'po_date' => $row->po_date->toDateString(), 'supplier_id' => $row->supplier_id, 'store_id' => $row->store_id,
            'cs_no' => $row->comparison_id ? YarnComparison::query()->whereKey($row->comparison_id)->value('cs_no') : null,
            'requisition_no' => $row->requisition_id ? YarnRequisition::query()->whereKey($row->requisition_id)->value('requisition_no') : null,
            'buyer_id' => $row->buyer_id, 'order_id' => $row->order_id, 'source' => $row->source, 'method' => $row->method, 'inco_term' => $row->inco_term,
            'payment_term' => $row->payment_term, 'ship_mode' => $row->ship_mode, 'currency_id' => $row->currency_id, 'conv_rate' => $row->conv_rate,
            'delivery_date' => $row->delivery_date?->toDateString(), 'shipment_date' => $row->shipment_date?->toDateString(), 'remarks' => $row->remarks,
            'status' => $row->status, 'editable' => $row->status === 'DRAFT',
            'store' => Store::query()->whereKey($row->store_id)->value('name'),
            'lines' => $lines->map(fn (YarnPurchaseOrderLine $l) => collect($l->only([...YarnProcurement::YARN, 'lot_no']))->all() + [
                'composition' => $names['compositions'][$l->composition_id] ?? null, 'count' => $names['counts'][$l->yarn_count_id] ?? null,
                'knit_start_date' => $l->knit_start_date?->toDateString(), 'ship_date' => $l->ship_date?->toDateString(),
                'qty' => (float) $l->qty, 'rate' => (float) $l->rate, 'amount' => round((float) $l->qty * (float) $l->rate, 2), 'received_qty' => (float) $l->received_qty,
            ])->values()->all(),
        ];
    }
}
