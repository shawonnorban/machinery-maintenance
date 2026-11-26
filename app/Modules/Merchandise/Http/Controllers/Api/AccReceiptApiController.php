<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Accounting\Models\AccAccount;
use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\AccPurchaseOrder;
use App\Modules\Merchandise\Models\AccPurchaseOrderLine;
use App\Modules\Merchandise\Models\AccReceipt;
use App\Modules\Merchandise\Models\AccReceiptCost;
use App\Modules\Merchandise\Models\AccReceiptLine;
use App\Modules\Merchandise\Models\AccRequisition;
use App\Modules\Merchandise\Models\ProcCostHead;
use App\Modules\Merchandise\Models\ProcPostingSetup;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
use App\Modules\Merchandise\Support\StockPeriods;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Trims & Accessories → Accessories Receipt Report (AnnotechERP 06/06), as
 * Brickland works it: a receipt (MRR) of what a supplier delivered to a
 * store — its master data, the received items, the other costs the delivery
 * carried (each head landed into stock value or not, inside the VAT base or
 * not) and the bill: discount, VAT, supplier TDS and VDS and the net payable,
 * recomputed on every save. The Receive List gives, supplier by supplier, the
 * purchase orders still waiting to be received; one taken from there fills
 * the lines with what is left of it. Nothing is received beyond what was
 * ordered. Finalize MRR locks the receipt, puts its goods on stock at landed
 * cost and moves its orders to Partial or Received.
 */
class AccReceiptApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $company = $this->caller()->companyId;
        MerchandiseDefaults::ensureCostHeads($company);
        $name = Company::query()->whereKey($company)->value('name');

        return ApiResponse::ok([
            'companies' => $name ? [['value' => $company, 'label' => $name]] : [],
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'stores' => Store::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'items' => MaterialItem::query()->where('kind', 'TRIMS')->where('active', true)->orderBy('name')->get(['id', 'name', 'item_code', 'uom'])
                ->map(fn ($i): array => ['value' => $i->id, 'label' => $i->item_code ? "{$i->name} ({$i->item_code})" : $i->name, 'uom' => $i->uom])->values()->all(),
            'cost_heads' => ProcCostHead::query()->where('active', true)->orderBy('sort_order')->get()
                ->map(fn ($h): array => ['value' => $h->id, 'label' => "{$h->name} ({$h->code})", 'landed' => $h->landed, 'vat_applicable' => $h->vat_applicable])->values()->all(),
            'orders' => AccPurchaseOrder::query()->where('receive_status', '!=', 'RECEIVED')->orderByDesc('po_no')->get(['id', 'po_no', 'supplier_id'])
                ->map(fn ($p): array => ['value' => $p->id, 'label' => $p->po_no, 'supplier_id' => $p->supplier_id])->values()->all(),
            'requisitions' => AccRequisition::query()->where('status', 'APPROVED')->orderByDesc('req_no')->get(['id', 'req_no'])->map(fn ($r): array => ['value' => $r->id, 'label' => $r->req_no])->values()->all(),
            'receipts' => AccReceipt::query()->orderByDesc('mrr_no')->get(['mrr_no'])->map(fn ($r): array => ['value' => $r->mrr_no, 'label' => $r->mrr_no])->values()->all(),
            'types' => AccReceipt::TYPES, 'requisition_types' => AccReceipt::REQUISITION_TYPES, 'sources' => AccPurchaseOrder::SOURCES,
            'payment_methods' => AccPurchaseOrder::PAYMENT_METHODS, 'currencies' => AccPurchaseOrder::CURRENCIES,
        ]);
    }

    /** The ledgers a cost head may be expensed on: the postable accounts of the chart, "1120 — Inventory — Trims & Accessories". */
    public function ledgers(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok(AccAccount::query()->where('postable', true)->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn ($a): array => ['value' => $a->id, 'label' => "{$a->code} — {$a->name}"])->values()->all());
    }

    /** Records: every receipt with its supplier, store, challan, order and bill. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = AccReceipt::query()->orderByDesc('mrr_date')->orderByDesc('mrr_no')->get();
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $pos = AccPurchaseOrder::query()->whereIn('id', $rows->pluck('po_id')->filter())->pluck('po_no', 'id');
        $reqs = AccRequisition::query()->whereIn('id', $rows->pluck('requisition_id')->filter())->pluck('req_no', 'id');
        $items = AccReceiptLine::query()->whereIn('mrr_id', $rows->pluck('id'))->selectRaw('mrr_id, count(*) as n')->groupBy('mrr_id')->pluck('n', 'mrr_id');

        return ApiResponse::ok($rows->map(fn (AccReceipt $r): array => [
            'mrr_no' => $r->mrr_no, 'mrr_date' => $r->mrr_date->toDateString(), 'mrr_type' => $r->mrr_type, 'supplier' => $suppliers[$r->supplier_id] ?? null,
            'store' => $stores[$r->store_id] ?? null, 'challan_no' => $r->challan_no, 'challan_date' => $r->challan_date->toDateString(),
            'po_no' => $r->po_id ? ($pos[$r->po_id] ?? null) : null, 'pr_no' => $r->requisition_id ? ($reqs[$r->requisition_id] ?? null) : null,
            'items' => (int) ($items[$r->id] ?? 0), 'landed_cost' => $r->landed_cost, 'net_payable' => $r->net_payable, 'currency' => $r->currency,
            'status' => $r->status, 'editable' => $r->isEditable(),
        ])->values()->all());
    }

    /** Receive List: the suppliers with orders still to receive, and the picked supplier's (`?supplier=`) orders. */
    public function receivables(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $pos = AccPurchaseOrder::query()->where('receive_status', '!=', 'RECEIVED')->orderBy('delivery_date')->orderBy('po_no')->get();
        $left = $this->leftOf($pos->pluck('id'));
        $pos = $pos->filter(fn (AccPurchaseOrder $p) => ($left[$p->id] ?? collect())->sum('left') > 0)->values();
        $suppliers = MaterialSupplier::query()->whereIn('id', $pos->pluck('supplier_id'))->orderBy('name')->get(['id', 'name', 'code']);
        $stores = Store::query()->whereIn('id', $pos->pluck('delivery_store_id')->filter())->pluck('name', 'id');
        $supplier = (string) $request->query('supplier', '');

        return ApiResponse::ok([
            'suppliers' => $suppliers->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name, 'count' => $pos->where('supplier_id', $s->id)->count()])->values()->all(),
            'orders' => $pos->where('supplier_id', $supplier)->values()->map(function (AccPurchaseOrder $p) use ($left, $stores): array {
                $mine = $left[$p->id] ?? collect();

                return [
                    'po_no' => $p->po_no, 'po_date' => $p->po_date->toDateString(), 'delivery_date' => $p->delivery_date->toDateString(), 'receive_status' => $p->receive_status,
                    'store' => $p->delivery_store_id ? ($stores[$p->delivery_store_id] ?? null) : null, 'ordered' => round((float) $mine->sum('ordered'), 4),
                    'received' => round((float) $mine->sum('received'), 4), 'left' => round((float) $mine->sum('left'), 4),
                ];
            })->all(),
        ]);
    }

    /** What a purchase order has left to receive, as a receipt's master data and lines. */
    public function fromOrder(string $order): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $po = AccPurchaseOrder::query()->where('po_no', $order)->where('receive_status', '!=', 'RECEIVED')->firstOrFail();
        $left = ($this->leftOf(collect([$po->id]))[$po->id] ?? collect())->where('left', '>', 0);
        $items = MaterialItem::query()->whereIn('id', $left->pluck('line.item_id'))->pluck('name', 'id');

        return ApiResponse::ok([
            'po_id' => $po->id, 'po_no' => $po->po_no, 'supplier_id' => $po->supplier_id, 'store_id' => $po->delivery_store_id, 'payment_method' => $po->payment_method,
            'source_type' => $po->source, 'currency' => $po->currency, 'conv_rate' => $po->conv_rate, 'requisition_id' => $po->requisition_id,
            'lines' => $left->map(fn (array $x): array => [
                'item_id' => $x['line']->item_id, 'po_line_id' => $x['line']->id, 'description' => $x['line']->specification ?: ($items[$x['line']->item_id] ?? null),
                'batch' => null, 'uom' => $x['line']->uom, 'qty' => $x['left'], 'rate' => $x['line']->unit_price,
            ])->values()->all(),
        ]);
    }

    public function show(string $receipt): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->present($this->find($receipt)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): AccReceipt {
            $row = AccReceipt::query()->create($this->header($data) + ['mrr_no' => $this->nextNumber($data['mrr_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $this->write($row, $data);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $receipt): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($receipt);
        $this->assertEditable($row);
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update($this->header($data));
            $this->write($row, $data);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $receipt): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($receipt);
        $this->assertEditable($row);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Finalize MRR: the receipt is locked, its goods go on stock at landed cost, and its orders move to Partial or Received. */
    public function finalize(string $receipt): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($receipt);
        $this->assertEditable($row);
        StockPeriods::assertOpen('trims', $row->mrr_date);
        // A company with no active posting setup is refused rather than posted somewhere by guess.
        if (! ProcPostingSetup::query()->where('unit_company_id', $row->unit_company_id)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_mrr.no_posting', ['company' => Company::query()->whereKey($row->unit_company_id)->value('name')])]);
        }
        DB::transaction(function () use ($row): void {
            $row->update(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => $this->caller()->auditUserId()]);
            $poIds = AccPurchaseOrderLine::query()->whereIn('id', AccReceiptLine::query()->where('mrr_id', $row->id)->whereNotNull('po_line_id')->pluck('po_line_id'))->pluck('po_id')
                ->push($row->po_id)->filter()->unique();
            $this->refreshOrders($poIds);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    private function find(string $receipt): AccReceipt
    {
        return AccReceipt::query()->where('mrr_no', $receipt)->firstOrFail();
    }

    private function assertEditable(AccReceipt $row): void
    {
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_mrr.locked')]);
        }
    }

    /**
     * Each order's lines with what is ordered, received (by every receipt but `$except`, draft or final) and left.
     *
     * @return Collection<string, Collection<int, array{line: AccPurchaseOrderLine, ordered: float, received: float, left: float}>>
     */
    private function leftOf(Collection $poIds, ?string $except = null): Collection
    {
        $lines = AccPurchaseOrderLine::query()->whereIn('po_id', $poIds)->orderBy('sort_order')->get();
        $received = AccReceiptLine::query()->whereIn('po_line_id', $lines->pluck('id'))->when($except, fn ($q) => $q->where('mrr_id', '!=', $except))
            ->selectRaw('po_line_id, sum(qty) as qty')->groupBy('po_line_id')->pluck('qty', 'po_line_id');

        return $lines->map(function (AccPurchaseOrderLine $l) use ($received): array {
            $got = (float) ($received[$l->id] ?? 0);

            return ['line' => $l, 'ordered' => $l->qty, 'received' => $got, 'left' => round(max(0, $l->qty - $got), 4)];
        })->groupBy(fn (array $x) => $x['line']->po_id);
    }

    /** An order's receive status from its finalised receipts: nothing, part of it, or all of it. */
    private function refreshOrders(Collection $poIds): void
    {
        $lines = AccPurchaseOrderLine::query()->whereIn('po_id', $poIds)->get(['id', 'po_id', 'qty']);
        $final = AccReceipt::query()->where('status', 'FINALIZED')->pluck('id');
        $got = AccReceiptLine::query()->whereIn('po_line_id', $lines->pluck('id'))->whereIn('mrr_id', $final)->selectRaw('po_line_id, sum(qty) as qty')->groupBy('po_line_id')->pluck('qty', 'po_line_id');
        foreach ($lines->groupBy('po_id') as $poId => $mine) {
            $received = $mine->sum(fn ($l) => (float) ($got[$l->id] ?? 0));
            $full = $mine->every(fn ($l) => (float) ($got[$l->id] ?? 0) + 0.00005 >= $l->qty);
            AccPurchaseOrder::query()->whereKey($poId)->update(['receive_status' => $received <= 0 ? 'PENDING' : ($full ? 'RECEIVED' : 'PARTIAL')]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AccReceipt $row): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $required = ['unit_company_id', 'supplier_id', 'store_id', 'mrr_date', 'payment_method', 'source_type', 'challan_no', 'challan_date', 'currency', 'mrr_type'];
        $data = $request->validate([
            'unit_company_id' => ['required', Rule::in([$company])],
            'supplier_id' => ['required', $in('mp_suppliers')],
            'store_id' => ['required', $in('stores')],
            'mrr_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(AccPurchaseOrder::PAYMENT_METHODS)],
            'source_type' => ['required', Rule::in(AccPurchaseOrder::SOURCES)],
            'challan_no' => ['required', 'string', 'max:60'],
            'challan_date' => ['required', 'date', 'before_or_equal:mrr_date'],
            'currency' => ['required', Rule::in(AccPurchaseOrder::CURRENCIES)],
            'conv_rate' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'po_id' => ['nullable', $in('merch_acc_pos')],
            'requisition_id' => ['nullable', Rule::exists('merch_acc_requisitions', 'id')->where('company_id', $company)->where('status', 'APPROVED')],
            'requisition_type' => ['nullable', Rule::in(AccReceipt::REQUISITION_TYPES)],
            'mrr_type' => ['required', Rule::in(AccReceipt::TYPES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'vat_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tds_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vds_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.item_id' => ['nullable', $in('mp_items')],
            // A line raised from a Material Procurement follow-up (08/06) keeps the requisition line it came from.
            'lines.*.mp_requisition_line_id' => ['nullable', $in('mp_requisition_lines')],
            'lines.*.po_line_id' => ['nullable', $in('merch_acc_po_lines')],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.batch' => ['nullable', 'string', 'max:60'],
            'lines.*.uom' => ['nullable', 'string', 'max:24', UomCodes::rule($this->caller()->companyId)],
            'lines.*.qty' => ['nullable', 'numeric', 'max:999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'costs' => ['present', 'array', 'max:50'],
            'costs.*.cost_head_id' => ['nullable', $in('merch_proc_cost_heads')],
            'costs.*.amount' => ['nullable', 'numeric', 'max:9999999999'],
            'costs.*.remarks' => ['nullable', 'string', 'max:200'],
        ], collect($required)->mapWithKeys(fn (string $f) => ["{$f}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.mrr.f.{$f}")])])->all() + [
            'requisition_id.exists' => __('merchandise.acc_po.not_approved'),
            'challan_date.before_or_equal' => __('merchandise.acc_mrr.challan_after'),
            'conv_rate.gt' => __('merchandise.acc_po.conv_rate'),
        ]);

        // validate() hands the rows back in rule order — a row missing an early key moves last; keep the order they came in.
        ksort($data['lines']);
        // A closed month's documents are no longer saved (Month Close, T14–T19).
        StockPeriods::assertOpen('trims', $data['mrr_date'], 'mrr_date');
        ksort($data['costs']);

        if ($data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => __('merchandise.acc_mrr.lines_required')]);
        }
        $poLines = AccPurchaseOrderLine::query()->whereIn('id', collect($data['lines'])->pluck('po_line_id')->filter())->get()->keyBy('id');
        $pos = AccPurchaseOrder::query()->whereIn('id', $poLines->pluck('po_id')->push($data['po_id'] ?? null)->filter()->unique())->get()->keyBy('id');
        if (! empty($data['po_id']) && $pos[$data['po_id']]->supplier_id !== $data['supplier_id']) {
            throw ValidationException::withMessages(['po_id' => __('merchandise.acc_mrr.other_supplier', ['no' => $pos[$data['po_id']]->po_no])]);
        }
        $left = $this->leftOf($poLines->pluck('po_id')->unique(), $row?->id)->flatten(1)->keyBy(fn (array $x) => $x['line']->id);
        $asked = [];
        $errors = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            if (empty($l['item_id'])) {
                $errors["lines.{$i}.item_id"] = __('merchandise.acc_mrr.row_item', ['n' => $n]);
            }
            if (! isset($l['qty']) || (float) $l['qty'] <= 0) {
                $errors["lines.{$i}.qty"] = __('merchandise.acc_mrr.row_qty', ['n' => $n]);

                continue;
            }
            $lineId = $l['po_line_id'] ?? null;
            if ($lineId) {
                $po = $pos[$poLines[$lineId]->po_id];
                if ($po->supplier_id !== $data['supplier_id']) {
                    $errors["lines.{$i}.po_line_id"] = __('merchandise.acc_mrr.row_other_supplier', ['n' => $n, 'no' => $po->po_no]);

                    continue;
                }
                // The same order line on two rows of one receipt counts once, in full.
                $asked[$lineId] = ($asked[$lineId] ?? 0) + (float) $l['qty'];
                $available = (float) ($left[$lineId]['left'] ?? 0);
                if ($asked[$lineId] > $available + 0.00005) {
                    $errors["lines.{$i}.qty"] = __('merchandise.acc_mrr.row_over', ['n' => $n, 'left' => rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.'), 'no' => $po->po_no]);
                }
            }
        }
        foreach (array_values($data['costs']) as $i => $c) {
            if (empty($c['cost_head_id'])) {
                $errors["costs.{$i}.cost_head_id"] = __('merchandise.acc_mrr.cost_head', ['n' => $i + 1]);
            }
            if (! isset($c['amount']) || (float) $c['amount'] <= 0) {
                $errors["costs.{$i}.amount"] = __('merchandise.acc_mrr.cost_amount', ['n' => $i + 1]);
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function header(array $data): array
    {
        $blank = fn (string $k): ?string => isset($data[$k]) && trim((string) $data[$k]) !== '' ? trim((string) $data[$k]) : null;

        return [
            'mrr_type' => $data['mrr_type'], 'unit_company_id' => $data['unit_company_id'], 'supplier_id' => $data['supplier_id'], 'store_id' => $data['store_id'],
            'mrr_date' => $data['mrr_date'], 'payment_method' => $data['payment_method'], 'source_type' => $data['source_type'], 'challan_no' => trim($data['challan_no']),
            'challan_date' => $data['challan_date'], 'currency' => $data['currency'], 'conv_rate' => $data['currency'] === 'BDT' ? 1 : (float) ($data['conv_rate'] ?? 1),
            'po_id' => $data['po_id'] ?? null, 'requisition_id' => $data['requisition_id'] ?? null, 'requisition_type' => $data['requisition_type'] ?? null, 'remarks' => $blank('remarks'),
            'discount_pct' => (float) ($data['discount_pct'] ?? 0), 'discount_amount' => (float) ($data['discount_amount'] ?? 0),
            'vat_pct' => (float) ($data['vat_pct'] ?? 0), 'tds_pct' => (float) ($data['tds_pct'] ?? 0), 'vds_pct' => (float) ($data['vds_pct'] ?? 0),
        ];
    }

    /** The lines and other costs as sent, and the bill recomputed from them; each line's share of the landed cost. */
    private function write(AccReceipt $row, array $data): void
    {
        AccReceiptLine::query()->where('mrr_id', $row->id)->delete();
        AccReceiptCost::query()->where('mrr_id', $row->id)->delete();
        $heads = ProcCostHead::query()->whereIn('id', collect($data['costs'])->pluck('cost_head_id'))->get()->keyBy('id');
        $items = MaterialItem::query()->whereIn('id', collect($data['lines'])->pluck('item_id'))->get(['id', 'name', 'uom'])->keyBy('id');
        $costs = collect(array_values($data['costs']))->map(fn (array $c): array => ['head' => $heads[$c['cost_head_id']], 'amount' => round((float) $c['amount'], 2), 'remarks' => trim((string) ($c['remarks'] ?? '')) ?: null]);
        $lines = collect(array_values($data['lines']))->map(fn (array $l): array => $l + ['amount' => round((float) $l['qty'] * (float) ($l['rate'] ?? 0), 2)]);

        $goods = round((float) $lines->sum('amount'), 2);
        $discount = round($row->discount_pct > 0 ? $goods * $row->discount_pct / 100 : min($row->discount_amount, $goods), 2);
        $other = round((float) $costs->sum('amount'), 2);
        $landedCosts = round((float) $costs->filter(fn ($c) => $c['head']->landed)->sum('amount'), 2);
        $vatBase = round($goods - $discount + (float) $costs->filter(fn ($c) => $c['head']->vat_applicable)->sum('amount'), 2);
        $vat = round($vatBase * $row->vat_pct / 100, 2);
        // TDS on goods less discount plus other costs, VAT excluded; VDS on the VAT base, never more than the VAT itself.
        $tds = round(($goods - $discount + $other) * $row->tds_pct / 100, 2);
        $vds = round(min($vatBase * $row->vds_pct / 100, $vat), 2);
        $landed = round($goods - $discount + $landedCosts, 2);

        foreach ($lines as $i => $l) {
            $item = $items[$l['item_id']];
            AccReceiptLine::query()->create([
                'mrr_id' => $row->id, 'item_id' => $item->id, 'po_line_id' => $l['po_line_id'] ?? null, 'mp_requisition_line_id' => $l['mp_requisition_line_id'] ?? null, 'description' => trim((string) ($l['description'] ?? '')) ?: $item->name,
                'batch' => trim((string) ($l['batch'] ?? '')) ?: null, 'uom' => UomCodes::normalize($l['uom'] ?? null) ?? UomCodes::normalize($item->uom), 'qty' => round((float) $l['qty'], 4),
                'rate' => round((float) ($l['rate'] ?? 0), 4), 'amount' => $l['amount'],
                // Its share of the landed cost, by value; with no value, by quantity.
                'landed_amount' => round($goods > 0 ? $landed * $l['amount'] / $goods : $landed * (float) $l['qty'] / max((float) $lines->sum(fn ($x) => (float) $x['qty']), 1), 2),
                'sort_order' => $i + 1,
            ]);
        }
        foreach ($costs as $i => $c) {
            AccReceiptCost::query()->create(['mrr_id' => $row->id, 'cost_head_id' => $c['head']->id, 'landed' => $c['head']->landed, 'vat_applicable' => $c['head']->vat_applicable, 'amount' => $c['amount'], 'remarks' => $c['remarks'], 'sort_order' => $i + 1]);
        }
        $row->update([
            'goods_value' => $goods, 'other_cost' => $other, 'landed_cost' => $landed, 'vat_base' => $vatBase, 'vat' => $vat, 'tds' => $tds, 'vds' => $vds,
            'net_payable' => round($goods - $discount + $other + $vat - $tds - $vds, 2),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(AccReceipt $row): array
    {
        $lines = AccReceiptLine::query()->where('mrr_id', $row->id)->orderBy('sort_order')->get();
        $costs = AccReceiptCost::query()->where('mrr_id', $row->id)->orderBy('sort_order')->get();
        $poOf = AccPurchaseOrderLine::query()->whereIn('id', $lines->pluck('po_line_id')->filter())->pluck('po_id', 'id');
        $poNos = AccPurchaseOrder::query()->whereIn('id', $poOf->values()->push($row->po_id)->filter()->unique())->pluck('po_no', 'id');

        return [
            'mrr_no' => $row->mrr_no, 'mrr_type' => $row->mrr_type, 'unit_company_id' => $row->unit_company_id, 'supplier_id' => $row->supplier_id, 'store_id' => $row->store_id,
            'mrr_date' => $row->mrr_date->toDateString(), 'payment_method' => $row->payment_method, 'source_type' => $row->source_type, 'challan_no' => $row->challan_no,
            'challan_date' => $row->challan_date->toDateString(), 'currency' => $row->currency, 'conv_rate' => $row->conv_rate, 'po_id' => $row->po_id,
            'po_no' => $row->po_id ? ($poNos[$row->po_id] ?? null) : null, 'requisition_id' => $row->requisition_id, 'requisition_type' => $row->requisition_type, 'remarks' => $row->remarks,
            'discount_pct' => $row->discount_pct, 'discount_amount' => $row->discount_amount, 'vat_pct' => $row->vat_pct, 'tds_pct' => $row->tds_pct, 'vds_pct' => $row->vds_pct,
            'goods_value' => $row->goods_value, 'other_cost' => $row->other_cost, 'landed_cost' => $row->landed_cost, 'vat_base' => $row->vat_base, 'vat' => $row->vat,
            'tds' => $row->tds, 'vds' => $row->vds, 'net_payable' => $row->net_payable, 'voucher_no' => $row->voucher_no, 'status' => $row->status, 'editable' => $row->isEditable(),
            'lines' => $lines->map(fn (AccReceiptLine $l): array => [
                'item_id' => $l->item_id, 'po_line_id' => $l->po_line_id, 'mp_requisition_line_id' => $l->mp_requisition_line_id, 'po_no' => $l->po_line_id ? ($poNos[$poOf[$l->po_line_id] ?? ''] ?? null) : null,
                'description' => $l->description, 'batch' => $l->batch, 'uom' => $l->uom, 'qty' => $l->qty, 'rate' => $l->rate, 'amount' => $l->amount, 'landed_amount' => $l->landed_amount,
            ])->values()->all(),
            'costs' => $costs->map(fn (AccReceiptCost $c): array => ['cost_head_id' => $c->cost_head_id, 'landed' => $c->landed, 'vat_applicable' => $c->vat_applicable, 'amount' => $c->amount, 'remarks' => $c->remarks])->values()->all(),
        ];
    }

    /** MRR-ACC-2026100001: the year and month, then the company's next receipt that month; held to the end of the save. */
    public static function nextNumber(string $date): string
    {
        $head = 'MRR-ACC-'.CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_acc_mrrs:'.$head.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) AccReceipt::query()->where('mrr_no', 'like', $head.'%')->pluck('mrr_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
