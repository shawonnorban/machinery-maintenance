<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\AccPurchaseOrder;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FabricReturnLine;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\ProcCostHead;
use App\Modules\Merchandise\Models\ProcPostingSetup;
use App\Modules\Merchandise\Models\PurchasedFabricIndent;
use App\Modules\Merchandise\Models\PurchasedFabricLedger;
use App\Modules\Merchandise\Models\PurchasedFabricMrr;
use App\Modules\Merchandise\Models\PurchasedFabricMrrCost;
use App\Modules\Merchandise\Models\PurchasedFabricMrrLine;
use App\Modules\Merchandise\Models\PurchasedFabricPo;
use App\Modules\Merchandise\Models\PurchasedFabricPoLine;
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
 * Trims & Accessories → Purchased Fabric Receipt Report (AnnotechERP 06/06),
 * as Brickland works it: the order that has arrived picked off the Receive
 * List fills the receipt with what it was placed for; each batch is received,
 * part of it perhaps rejected, the rest accepted — never more accepted than
 * the order has open. The other costs and the bill (discount, VAT, TDS, VDS)
 * are recomputed on save; numbered MRR-IMF-202610 0001. Finalize MRR locks
 * the receipt, puts the accepted fabric on the purchased-fabric stock ledger
 * at landed cost and moves its order to Partial or Received.
 */
class PurchasedFabricMrrApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $company = $this->caller()->companyId;
        MerchandiseDefaults::ensureCostHeads($company);
        $name = Company::query()->whereKey($company)->value('name');
        $pos = PurchasedFabricPo::query()->where('receive_status', '!=', 'RECEIVED')->orderByDesc('po_date')->orderByDesc('po_no')->get();
        $left = $this->leftOf($pos->pluck('id'));
        $open = $pos->filter(fn ($p) => ($left[$p->id] ?? collect())->sum('left') > 0);

        return ApiResponse::ok([
            'companies' => $name ? [['value' => $company, 'label' => $name]] : [],
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'stores' => Store::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'cost_heads' => ProcCostHead::query()->where('active', true)->orderBy('sort_order')->get()->map(fn ($h): array => ['value' => $h->id, 'label' => "{$h->name} ({$h->code})", 'landed' => $h->landed, 'vat_applicable' => $h->vat_applicable])->values()->all(),
            // The Receive List: fabric orders with something still to receive.
            'receivable' => $open->map(fn ($p): array => ['po_no' => $p->po_no, 'po_date' => $p->po_date->toDateString(), 'supplier_id' => $p->supplier_id])->values()->all(),
            'open_lines' => $open->flatMap(fn ($p) => ($left[$p->id] ?? collect())->where('left', '>', 0)->map(fn (array $x): array => $this->lineFrom($p, $x)))->values()->all(),
            'fabric_types' => FabricType::query()->orderBy('name')->get(['id', 'name'])->map(fn ($t): array => ['value' => $t->id, 'label' => $t->name])->values()->all(),
            'compositions' => FiberComposition::query()->orderBy('description')->get(['id', 'description'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->description])->values()->all(),
            'colours' => Colour::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->name])->values()->all(),
            'receipts' => PurchasedFabricMrr::query()->orderByDesc('mrr_no')->get(['mrr_no'])->map(fn ($r): array => ['value' => $r->mrr_no, 'label' => $r->mrr_no])->values()->all(),
            'sources' => AccPurchaseOrder::SOURCES, 'payment_methods' => AccPurchaseOrder::PAYMENT_METHODS, 'currencies' => AccPurchaseOrder::CURRENCIES,
        ]);
    }

    /** Records: every receipt with its order, parties, quantities and bill. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = PurchasedFabricMrr::query()->orderByDesc('mrr_date')->orderByDesc('mrr_no')->get();
        $lines = PurchasedFabricMrrLine::query()->whereIn('mrr_id', $rows->pluck('id'))->get(['id', 'mrr_id', 'po_line_id', 'receive_qty'])->groupBy('mrr_id');
        $poLines = PurchasedFabricPoLine::query()->whereIn('id', $lines->flatten()->pluck('po_line_id'))->pluck('indent_id', 'id');
        $reqs = PurchasedFabricIndent::query()->whereIn('id', $poLines->values()->unique())->pluck('req_no', 'id');
        $pos = PurchasedFabricPo::query()->whereIn('id', $rows->pluck('po_id')->filter())->pluck('po_no', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');
        $stores = Store::query()->whereIn('id', $rows->pluck('store_id'))->pluck('name', 'id');
        $companies = Company::query()->whereIn('id', $rows->pluck('unit_company_id'))->pluck('name', 'id');
        $returned = $this->returnedOf($lines->flatten()->pluck('id'));

        return ApiResponse::ok($rows->map(function (PurchasedFabricMrr $r) use ($lines, $poLines, $reqs, $pos, $suppliers, $stores, $companies, $returned): array {
            $mine = $lines[$r->id] ?? collect();

            return [
                'mrr_no' => $r->mrr_no, 'mrr_date' => $r->mrr_date->toDateString(), 'finalized' => ! $r->isEditable(), 'accounting' => $r->voucher_no !== null,
                'pr' => $mine->map(fn ($l) => $reqs[$poLines[$l->po_line_id] ?? ''] ?? null)->filter()->unique()->implode(', ') ?: null,
                'po' => $r->po_id ? ($pos[$r->po_id] ?? null) : null, 'company' => $companies[$r->unit_company_id] ?? null, 'supplier' => $suppliers[$r->supplier_id] ?? null,
                'store' => $stores[$r->store_id] ?? null, 'payment_method' => $r->payment_method, 'source_type' => $r->source_type, 'lc_no' => $r->lc_no,
                'rcv_qty' => round((float) $mine->sum('receive_qty'), 4), 'ret_qty' => round((float) $mine->sum(fn ($l) => $returned[$l->id] ?? 0), 4),
                'net_amount' => $r->net_amount, 'remarks' => $r->remarks, 'goods_value' => $r->goods_value, 'vat' => $r->vat, 'tds_vds' => round($r->tds + $r->vds, 2),
                'net_payable' => $r->net_payable, 'voucher_no' => $r->voucher_no, 'currency' => $r->currency, 'status' => $r->status,
            ];
        })->values()->all());
    }

    /** What a fabric order has left to receive, as a receipt's master data and lines. */
    public function fromOrder(string $order): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $po = PurchasedFabricPo::query()->where('po_no', $order)->where('receive_status', '!=', 'RECEIVED')->firstOrFail();
        $left = ($this->leftOf(collect([$po->id]))[$po->id] ?? collect())->where('left', '>', 0);

        return ApiResponse::ok([
            'po_id' => $po->id, 'po_no' => $po->po_no, 'supplier_id' => $po->supplier_id, 'store_id' => $po->delivery_store_id, 'payment_method' => $po->payment_method,
            'source_type' => $po->source, 'currency' => $po->currency, 'conv_rate' => $po->conv_rate,
            'lines' => $left->map(fn (array $x): array => $this->lineFrom($po, $x))->values()->all(),
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
        $row = DB::transaction(function () use ($data): PurchasedFabricMrr {
            $row = PurchasedFabricMrr::query()->create($this->header($data) + ['mrr_no' => $this->nextNumber($data['mrr_date']), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
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

    /** Finalize MRR: locked; the accepted fabric goes on the purchased-fabric stock ledger at landed cost; its orders move to Partial or Received. */
    public function finalize(string $receipt): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($receipt);
        $this->assertEditable($row);
        StockPeriods::assertOpen('purchased-fabric', $row->mrr_date);
        if (! ProcPostingSetup::query()->where('unit_company_id', $row->unit_company_id)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_mrr.no_posting', ['company' => Company::query()->whereKey($row->unit_company_id)->value('name')])]);
        }
        DB::transaction(function () use ($row): void {
            $row->update(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => $this->caller()->auditUserId()]);
            $lines = PurchasedFabricMrrLine::query()->where('mrr_id', $row->id)->orderBy('sort_order')->get();
            foreach ($lines->where('accept_qty', '>', 0) as $l) {
                PurchasedFabricLedger::query()->create([
                    'store_id' => $row->store_id, 'source' => 'MRR', 'source_id' => $row->id, 'source_line_id' => $l->id, 'txn_date' => $row->mrr_date->toDateString(),
                    'batch_no' => $l->batch_no, 'fabric_item' => $l->description ?: (FabricType::query()->whereKey($l->fabric_type_id)->value('name') ?? '—'), 'description' => $l->description,
                    'colour_id' => $l->colour_id, 'fabric_type_id' => $l->fabric_type_id, 'composition_id' => $l->composition_id, 'uom' => $l->uom, 'qty' => $l->accept_qty,
                    'cost_bdt' => round($l->landed_amount * ($row->currency === 'BDT' ? 1 : $row->conv_rate), 2),
                ]);
            }
            $this->refreshOrders(PurchasedFabricPoLine::query()->whereIn('id', $lines->pluck('po_line_id'))->pluck('po_id')->push($row->po_id)->filter()->unique());
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    private function find(string $receipt): PurchasedFabricMrr
    {
        return PurchasedFabricMrr::query()->where('mrr_no', $receipt)->firstOrFail();
    }

    private function assertEditable(PurchasedFabricMrr $row): void
    {
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_mrr.locked')]);
        }
    }

    /** @return array<string, mixed> */
    private function lineFrom(PurchasedFabricPo $po, array $x): array
    {
        $l = $x['line'];

        return [
            'po_line_id' => $l->id, 'po_no' => $po->po_no, 'fabric_type_id' => $l->fabric_type_id, 'description' => $l->description, 'composition_id' => $l->composition_id,
            'colour_id' => $l->colour_id, 'gsm' => $l->gsm, 'dia' => $l->dia, 'uom' => $l->uom, 'po_qty' => $l->qty, 'prev_received' => $x['received'],
            'receive_qty' => $x['left'], 'reject_qty' => 0, 'rate' => $l->rate, 'batch_no' => null, 'rolls' => null, 'reject_reason' => null,
        ];
    }

    /**
     * Each order's lines with what is ordered, accepted by every receipt but `$except` (draft or final) and left.
     *
     * @return Collection<string, Collection<int, array{line: PurchasedFabricPoLine, received: float, left: float}>>
     */
    private function leftOf(Collection $poIds, ?string $except = null): Collection
    {
        $lines = PurchasedFabricPoLine::query()->whereIn('po_id', $poIds)->orderBy('sort_order')->get();
        $got = PurchasedFabricMrrLine::query()->whereIn('po_line_id', $lines->pluck('id'))->when($except, fn ($q) => $q->where('mrr_id', '!=', $except))
            ->selectRaw('po_line_id, sum(accept_qty) as qty')->groupBy('po_line_id')->pluck('qty', 'po_line_id');

        return $lines->map(function (PurchasedFabricPoLine $l) use ($got): array {
            $received = round((float) ($got[$l->id] ?? 0), 4);

            return ['line' => $l, 'received' => $received, 'left' => round(max(0, $l->qty - $received), 4)];
        })->groupBy(fn (array $x) => $x['line']->po_id);
    }

    /** What finalised fabric return challans sent back of each receipt line. */
    private function returnedOf(Collection $mrrLineIds): Collection
    {
        if (! class_exists(FabricReturnLine::class)) {
            return collect();
        }

        return FabricReturnLine::query()->whereIn('mrr_line_id', $mrrLineIds)->selectRaw('mrr_line_id, sum(qty) as qty')->groupBy('mrr_line_id')->pluck('qty', 'mrr_line_id');
    }

    /** An order's receive status from its finalised receipts' accepted fabric: nothing, part of it, or all of it. */
    private function refreshOrders(Collection $poIds): void
    {
        $lines = PurchasedFabricPoLine::query()->whereIn('po_id', $poIds)->get(['id', 'po_id', 'qty']);
        $final = PurchasedFabricMrr::query()->where('status', 'FINALIZED')->select('id');
        $got = PurchasedFabricMrrLine::query()->whereIn('po_line_id', $lines->pluck('id'))->whereIn('mrr_id', $final)->selectRaw('po_line_id, sum(accept_qty) as qty')->groupBy('po_line_id')->pluck('qty', 'po_line_id');
        foreach ($lines->groupBy('po_id') as $poId => $mine) {
            $received = $mine->sum(fn ($l) => (float) ($got[$l->id] ?? 0));
            $full = $mine->every(fn ($l) => (float) ($got[$l->id] ?? 0) + 0.00005 >= $l->qty);
            PurchasedFabricPo::query()->whereKey($poId)->update(['receive_status' => $received <= 0 ? 'PENDING' : ($full ? 'RECEIVED' : 'PARTIAL')]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PurchasedFabricMrr $row): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $required = ['unit_company_id', 'supplier_id', 'store_id', 'mrr_date', 'payment_method', 'source_type', 'challan_no', 'challan_date', 'currency'];
        $data = $request->validate([
            'unit_company_id' => ['required', Rule::in([$company])],
            'supplier_id' => ['required', $in('mp_suppliers')],
            'store_id' => ['required', $in('stores')],
            'po_id' => ['nullable', $in('merch_pfab_pos')],
            'mrr_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(AccPurchaseOrder::PAYMENT_METHODS)],
            'source_type' => ['required', Rule::in(AccPurchaseOrder::SOURCES)],
            'challan_no' => ['required', 'string', 'max:60'],
            'challan_date' => ['required', 'date', 'before_or_equal:mrr_date'],
            'currency' => ['required', Rule::in(AccPurchaseOrder::CURRENCIES)],
            'conv_rate' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'lc_no' => ['nullable', 'string', 'max:60'],
            'lc_date' => ['nullable', 'date'],
            'bill_entry_no' => ['nullable', 'string', 'max:60'],
            'bill_entry_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'vat_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tds_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vds_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.po_line_id' => ['nullable', $in('merch_pfab_po_lines')],
            // A line raised from a Material Procurement follow-up (08/06) has no order line: it keeps the requisition line it came from.
            'lines.*.mp_requisition_line_id' => ['nullable', $in('mp_requisition_lines')],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.uom' => ['nullable', 'string', 'max:24'],
            'lines.*.batch_no' => ['nullable', 'string', 'max:60'],
            'lines.*.rolls' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'lines.*.receive_qty' => ['nullable', 'numeric', 'max:999999999'],
            'lines.*.reject_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.reject_reason' => ['nullable', 'string', 'max:200'],
            'costs' => ['present', 'array', 'max:50'],
            'costs.*.cost_head_id' => ['nullable', $in('merch_proc_cost_heads')],
            'costs.*.amount' => ['nullable', 'numeric', 'max:9999999999'],
            'costs.*.remarks' => ['nullable', 'string', 'max:200'],
        ], collect($required)->mapWithKeys(fn (string $f) => ["{$f}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.mrr.f.{$f}")])])->all() + [
            'challan_date.before_or_equal' => __('merchandise.acc_mrr.challan_after'),
            'conv_rate.gt' => __('merchandise.acc_po.conv_rate'),
        ]);
        // validate() hands the rows back in rule order — a row missing an early key moves last; keep the order they came in.
        ksort($data['lines']);
        // A closed month's documents are no longer saved (Month Close, T14–T19).
        StockPeriods::assertOpen('purchased-fabric', $data['mrr_date'], 'mrr_date');
        ksort($data['costs']);

        if ($data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => __('merchandise.acc_mrr.lines_required')]);
        }
        if (! empty($data['po_id']) && PurchasedFabricPo::query()->whereKey($data['po_id'])->value('supplier_id') !== $data['supplier_id']) {
            throw ValidationException::withMessages(['po_id' => __('merchandise.acc_mrr.other_supplier', ['no' => PurchasedFabricPo::query()->whereKey($data['po_id'])->value('po_no')])]);
        }
        $poLines = PurchasedFabricPoLine::query()->whereIn('id', collect($data['lines'])->pluck('po_line_id')->filter())->get()->keyBy('id');
        $pos = PurchasedFabricPo::query()->whereIn('id', $poLines->pluck('po_id')->unique())->get()->keyBy('id');
        $left = $this->leftOf($poLines->pluck('po_id')->unique(), $row?->id)->flatten(1)->keyBy(fn (array $x) => $x['line']->id);
        $asked = [];
        $errors = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            $lineId = $l['po_line_id'] ?? null;
            $receive = (float) ($l['receive_qty'] ?? 0);
            $reject = (float) ($l['reject_qty'] ?? 0);
            if (! $lineId && ! empty($l['mp_requisition_line_id'])) {
                if ($receive <= 0) {
                    $errors["lines.{$i}.receive_qty"] = __('merchandise.fmrr.row_receive', ['n' => $n]);
                } elseif ($reject > $receive) {
                    $errors["lines.{$i}.reject_qty"] = __('merchandise.fmrr.row_reject', ['n' => $n]);
                }

                continue;
            }
            if (! $lineId) {
                $errors["lines.{$i}.po_line_id"] = __('merchandise.fmrr.row_po', ['n' => $n]);

                continue;
            }
            if ($receive <= 0) {
                $errors["lines.{$i}.receive_qty"] = __('merchandise.fmrr.row_receive', ['n' => $n]);

                continue;
            }
            if ($reject > $receive) {
                $errors["lines.{$i}.reject_qty"] = __('merchandise.fmrr.row_reject', ['n' => $n]);

                continue;
            }
            $po = $pos[$poLines[$lineId]->po_id];
            if ($po->supplier_id !== $data['supplier_id']) {
                $errors["lines.{$i}.po_line_id"] = __('merchandise.acc_mrr.row_other_supplier', ['n' => $n, 'no' => $po->po_no]);

                continue;
            }
            // The same order line on two rows counts once, in full; only the accepted part is received.
            $asked[$lineId] = ($asked[$lineId] ?? 0) + $receive - $reject;
            $available = (float) ($left[$lineId]['left'] ?? 0);
            if ($asked[$lineId] > $available + 0.00005) {
                $errors["lines.{$i}.receive_qty"] = __('merchandise.fmrr.row_over', ['n' => $n, 'left' => rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.'), 'no' => $po->po_no]);
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
            'unit_company_id' => $data['unit_company_id'], 'supplier_id' => $data['supplier_id'], 'store_id' => $data['store_id'], 'po_id' => $data['po_id'] ?? null,
            'mrr_date' => $data['mrr_date'], 'payment_method' => $data['payment_method'], 'source_type' => $data['source_type'], 'challan_no' => trim($data['challan_no']),
            'challan_date' => $data['challan_date'], 'currency' => $data['currency'], 'conv_rate' => $data['currency'] === 'BDT' ? 1 : (float) ($data['conv_rate'] ?? 1),
            'lc_no' => $blank('lc_no'), 'lc_date' => $data['lc_date'] ?? null, 'bill_entry_no' => $blank('bill_entry_no'), 'bill_entry_date' => $data['bill_entry_date'] ?? null,
            'remarks' => $blank('remarks'), 'discount_pct' => (float) ($data['discount_pct'] ?? 0), 'discount_amount' => (float) ($data['discount_amount'] ?? 0),
            'vat_pct' => (float) ($data['vat_pct'] ?? 0), 'tds_pct' => (float) ($data['tds_pct'] ?? 0), 'vds_pct' => (float) ($data['vds_pct'] ?? 0),
        ];
    }

    /** The batches (each carrying its PO line's fabric) and costs as sent, the bill recomputed from the accepted quantities, each batch's share of the landed cost. */
    private function write(PurchasedFabricMrr $row, array $data): void
    {
        PurchasedFabricMrrLine::query()->where('mrr_id', $row->id)->delete();
        PurchasedFabricMrrCost::query()->where('mrr_id', $row->id)->delete();
        $source = PurchasedFabricPoLine::query()->whereIn('id', collect($data['lines'])->pluck('po_line_id'))->get()->keyBy('id');
        $heads = ProcCostHead::query()->whereIn('id', collect($data['costs'])->pluck('cost_head_id'))->get()->keyBy('id');
        $lines = collect(array_values($data['lines']))->map(function (array $l) use ($source): array {
            $accept = round((float) $l['receive_qty'] - (float) ($l['reject_qty'] ?? 0), 4);

            return $l + ['src' => $source[$l['po_line_id'] ?? ''] ?? null, 'accept' => $accept, 'amount' => round($accept * (float) ($l['rate'] ?? 0), 2)];
        });
        $costs = collect(array_values($data['costs']))->map(fn (array $c): array => ['head' => $heads[$c['cost_head_id']], 'amount' => round((float) $c['amount'], 2), 'remarks' => trim((string) ($c['remarks'] ?? '')) ?: null]);

        $goods = round((float) $lines->sum('amount'), 2);
        $discount = round($row->discount_pct > 0 ? $goods * $row->discount_pct / 100 : min($row->discount_amount, $goods), 2);
        $other = round((float) $costs->sum('amount'), 2);
        $landed = round($goods - $discount + (float) $costs->filter(fn ($c) => $c['head']->landed)->sum('amount'), 2);
        $vatBase = round($goods - $discount + (float) $costs->filter(fn ($c) => $c['head']->vat_applicable)->sum('amount'), 2);
        $vat = round($vatBase * $row->vat_pct / 100, 2);
        // TDS on goods less discount plus other costs, VAT excluded; VDS on the VAT base, never more than the VAT itself.
        $tds = round(($goods - $discount + $other) * $row->tds_pct / 100, 2);
        $vds = round(min($vatBase * $row->vds_pct / 100, $vat), 2);
        $totalAccept = max((float) $lines->sum('accept'), 1);

        foreach ($lines as $i => $l) {
            $src = $l['src'];
            PurchasedFabricMrrLine::query()->create([
                'mrr_id' => $row->id, 'po_line_id' => $src?->id, 'fabric_type_id' => $src?->fabric_type_id, 'description' => $src ? $src->description : (trim((string) ($l['description'] ?? '')) ?: null),
                'composition_id' => $src?->composition_id, 'colour_id' => $src?->colour_id, 'gsm' => $src ? $src->gsm : $this->procuredGsm($l['mp_requisition_line_id'] ?? null), 'dia' => $src?->dia, 'batch_no' => trim((string) ($l['batch_no'] ?? '')) ?: null, 'rolls' => $l['rolls'] ?? null,
                'mp_requisition_line_id' => $src ? null : ($l['mp_requisition_line_id'] ?? null), 'uom' => $src ? $src->uom : ($l['uom'] ?? null), 'receive_qty' => round((float) $l['receive_qty'], 4), 'reject_qty' => round((float) ($l['reject_qty'] ?? 0), 4), 'accept_qty' => $l['accept'],
                'rate' => round((float) ($l['rate'] ?? 0), 4), 'amount' => $l['amount'],
                // Its share of the landed cost, by value; with no value, by accepted quantity.
                'landed_amount' => round($goods > 0 ? $landed * $l['amount'] / $goods : $landed * $l['accept'] / $totalAccept, 2),
                'reject_reason' => trim((string) ($l['reject_reason'] ?? '')) ?: null, 'sort_order' => $i + 1,
            ]);
        }
        foreach ($costs as $i => $c) {
            PurchasedFabricMrrCost::query()->create(['mrr_id' => $row->id, 'cost_head_id' => $c['head']->id, 'landed' => $c['head']->landed, 'vat_applicable' => $c['head']->vat_applicable, 'amount' => $c['amount'], 'remarks' => $c['remarks'], 'sort_order' => $i + 1]);
        }
        $net = round($goods - $discount + $other, 2);
        $row->update([
            'goods_value' => $goods, 'other_cost' => $other, 'landed_cost' => $landed, 'vat_base' => $vatBase, 'vat' => $vat, 'tds' => $tds, 'vds' => $vds,
            'net_amount' => $net, 'net_payable' => round($net + $vat - $tds - $vds, 2),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(PurchasedFabricMrr $row): array
    {
        $lines = PurchasedFabricMrrLine::query()->where('mrr_id', $row->id)->orderBy('sort_order')->get();
        $costs = PurchasedFabricMrrCost::query()->where('mrr_id', $row->id)->orderBy('sort_order')->get();
        $poLines = PurchasedFabricPoLine::query()->whereIn('id', $lines->pluck('po_line_id'))->get()->keyBy('id');
        $poNos = PurchasedFabricPo::query()->whereIn('id', $poLines->pluck('po_id')->push($row->po_id)->filter()->unique())->pluck('po_no', 'id');
        $left = $this->leftOf($poLines->pluck('po_id')->unique(), $row->id)->flatten(1)->keyBy(fn (array $x) => $x['line']->id);

        return $row->only([
            'mrr_no', 'unit_company_id', 'supplier_id', 'store_id', 'po_id', 'payment_method', 'source_type', 'challan_no', 'currency', 'conv_rate', 'lc_no', 'bill_entry_no', 'remarks',
            'discount_pct', 'discount_amount', 'vat_pct', 'tds_pct', 'vds_pct', 'goods_value', 'other_cost', 'landed_cost', 'vat_base', 'vat', 'tds', 'vds', 'net_amount', 'net_payable',
            'voucher_no', 'status',
        ]) + [
            'mrr_date' => $row->mrr_date->toDateString(), 'challan_date' => $row->challan_date->toDateString(), 'lc_date' => $row->lc_date?->toDateString(),
            'bill_entry_date' => $row->bill_entry_date?->toDateString(), 'editable' => $row->isEditable(), 'po_no' => $row->po_id ? ($poNos[$row->po_id] ?? null) : null,
            'lines' => $lines->map(fn (PurchasedFabricMrrLine $l): array => $l->only(['po_line_id', 'mp_requisition_line_id', 'fabric_type_id', 'description', 'composition_id', 'colour_id', 'gsm', 'dia', 'batch_no', 'rolls', 'uom', 'receive_qty', 'reject_qty', 'accept_qty', 'rate', 'amount', 'landed_amount', 'reject_reason']) + [
                'po_no' => $poNos[$poLines[$l->po_line_id]->po_id ?? ''] ?? null, 'po_qty' => $poLines[$l->po_line_id]->qty ?? 0, 'prev_received' => (float) ($left[$l->po_line_id]['received'] ?? 0),
            ])->values()->all(),
            'costs' => $costs->map(fn (PurchasedFabricMrrCost $c): array => $c->only(['cost_head_id', 'landed', 'vat_applicable', 'amount', 'remarks']))->values()->all(),
        ];
    }

    /** MRR-IMF-2026100001: the year and month, then the company's next fabric receipt that month; held to the end of the save. */
    public static function nextNumber(string $date): string
    {
        $head = 'MRR-IMF-'.CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_pfab_mrrs:'.$head.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) PurchasedFabricMrr::query()->where('mrr_no', 'like', $head.'%')->pluck('mrr_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** A Material Procurement line's GSM: its material's. */
    private function procuredGsm(?string $requisitionLine): ?string
    {
        return $requisitionLine ? DB::table('mp_requisition_lines')->join('mp_items', 'mp_items.id', '=', 'mp_requisition_lines.item_id')->where('mp_requisition_lines.id', $requisitionLine)->value('mp_items.gsm') : null;
    }
}
