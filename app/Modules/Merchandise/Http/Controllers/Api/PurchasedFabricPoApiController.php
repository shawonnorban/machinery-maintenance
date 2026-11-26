<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\AccPurchaseOrder;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Colour;
use App\Modules\Merchandise\Models\FabricType;
use App\Modules\Merchandise\Models\FiberComposition;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\ProcCostHead;
use App\Modules\Merchandise\Models\PurchasedFabricIndent;
use App\Modules\Merchandise\Models\PurchasedFabricIndentLine;
use App\Modules\Merchandise\Models\PurchasedFabricMrr;
use App\Modules\Merchandise\Models\PurchasedFabricMrrLine;
use App\Modules\Merchandise\Models\PurchasedFabricPo;
use App\Modules\Merchandise\Models\PurchasedFabricPoCost;
use App\Modules\Merchandise\Models\PurchasedFabricPoLine;
use App\Modules\Merchandise\Support\MerchandiseDefaults;
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
 * Trims & Accessories → Purchased Fabric Purchase Order (AnnotechERP 06/06),
 * as Brickland works it: an approved fabric indent picked off the Approved PR
 * List fills the details with what it has left open; each line orders an
 * approved indent line and cannot exceed what it left open. The other costs
 * agreed on the order, the discount, VAT, supplier TDS and VDS and an
 * adjustment give the Net PO Amount = PO Value − Discount + Other Cost + VAT
 * − TDS − VDS ± Adjustment, recomputed on save; numbered PO-FAB-202610 0001,
 * each change a new revision, an order received against no longer changed.
 */
class PurchasedFabricPoApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $company = $this->caller()->companyId;
        MerchandiseDefaults::ensureCostHeads($company);
        $name = Company::query()->whereKey($company)->value('name');
        $indents = PurchasedFabricIndent::query()->where('status', 'APPROVED')->orderByDesc('req_date')->orderByDesc('req_no')->get();
        $left = $this->leftOf($indents->pluck('id'));
        $orders = Order::query()->whereIn('id', $indents->pluck('order_id'))->pluck('order_no', 'id');
        $open = $indents->filter(fn ($i) => ($left[$i->id] ?? collect())->sum('left') > 0);

        return ApiResponse::ok([
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'stores' => Store::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'companies' => $name ? [['value' => $company, 'label' => $name]] : [],
            'cost_heads' => ProcCostHead::query()->where('active', true)->orderBy('sort_order')->get()->map(fn ($h): array => ['value' => $h->id, 'label' => "{$h->name} ({$h->code})", 'vat_applicable' => $h->vat_applicable])->values()->all(),
            'indents' => $indents->map(fn ($i): array => ['value' => $i->id, 'label' => $i->req_no])->values()->all(),
            // The Approved PR List: approved indents with something still open.
            'approved' => $open->map(fn ($i): array => ['req_no' => $i->req_no, 'req_date' => $i->req_date->toDateString(), 'order_no' => $orders[$i->order_id] ?? null, 'supplier_id' => $i->supplier_id])->values()->all(),
            // Each open indent line, for a line keyed by hand.
            'open_lines' => $open->flatMap(fn ($i) => ($left[$i->id] ?? collect())->where('left', '>', 0)->map(fn (array $x): array => $this->lineFrom($i, $x['line'], $x['left'])))->values()->all(),
            'fabric_types' => FabricType::query()->orderBy('name')->get(['id', 'name'])->map(fn ($t): array => ['value' => $t->id, 'label' => $t->name])->values()->all(),
            'compositions' => FiberComposition::query()->orderBy('description')->get(['id', 'description'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->description])->values()->all(),
            'colours' => Colour::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c): array => ['value' => $c->id, 'label' => $c->name])->values()->all(),
            'orders' => PurchasedFabricPo::query()->orderByDesc('po_no')->get(['po_no'])->map(fn ($p): array => ['value' => $p->po_no, 'label' => $p->po_no])->values()->all(),
            'sources' => AccPurchaseOrder::SOURCES, 'inco_terms' => AccPurchaseOrder::INCO_TERMS, 'payment_methods' => AccPurchaseOrder::PAYMENT_METHODS,
            'currencies' => AccPurchaseOrder::CURRENCIES, 'payment_terms' => AccPurchaseOrder::PAYMENT_TERMS, 'shipment_modes' => AccPurchaseOrder::SHIPMENT_MODES,
        ]);
    }

    /** Records: every order with its receive and revision status, requisitions, supplier, store, terms, group and net amount. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = PurchasedFabricPo::query()->orderByDesc('po_date')->orderByDesc('po_no')->get();
        $lines = PurchasedFabricPoLine::query()->whereIn('po_id', $rows->pluck('id'))->get(['po_id', 'indent_id'])->groupBy('po_id');
        $reqs = PurchasedFabricIndent::query()->whereIn('id', $rows->pluck('indent_id')->merge($lines->flatten()->pluck('indent_id'))->filter()->unique())->pluck('req_no', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');
        $stores = Store::query()->whereIn('id', $rows->pluck('delivery_store_id')->filter())->pluck('name', 'id');
        // The receipts each order's lines were received on.
        $poOf = PurchasedFabricPoLine::query()->whereIn('po_id', $rows->pluck('id'))->pluck('po_id', 'id');
        $mrrs = PurchasedFabricMrrLine::query()->whereIn('po_line_id', $poOf->keys())->get(['mrr_id', 'po_line_id']);
        $mrrNos = PurchasedFabricMrr::query()->whereIn('id', $mrrs->pluck('mrr_id')->unique())->pluck('mrr_no', 'id');
        $mrrsOf = $mrrs->groupBy(fn ($m) => $poOf[$m->po_line_id])->map(fn ($g) => $g->pluck('mrr_id')->unique()->map(fn ($id) => $mrrNos[$id] ?? null)->filter()->sort()->implode(', '));

        return ApiResponse::ok($rows->map(fn (PurchasedFabricPo $p): array => [
            'po_no' => $p->po_no, 'po_date' => $p->po_date->toDateString(), 'receive_status' => $p->receive_status, 'revision' => $p->revision,
            'pr_numbers' => collect([$p->indent_id])->merge(($lines[$p->id] ?? collect())->pluck('indent_id'))->filter()->unique()->map(fn ($id) => $reqs[$id] ?? null)->filter()->implode(', ') ?: null,
            'mrrs' => ($mrrsOf[$p->id] ?? '') ?: null, 'supplier' => $suppliers[$p->supplier_id] ?? null, 'store' => $p->delivery_store_id ? ($stores[$p->delivery_store_id] ?? null) : null,
            'source' => $p->source, 'inco_term' => $p->inco_term, 'payment_term' => $p->payment_term, 'payment_method' => $p->payment_method, 'po_group' => $p->po_group,
            'currency' => $p->currency, 'value' => $p->net_amount, 'editable' => $p->isEditable(),
        ])->values()->all());
    }

    /** Requisition List: the buyers with approved fabric indents still to order, and the picked buyer's (`?buyer=`) indents. */
    public function requisitions(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $indents = PurchasedFabricIndent::query()->where('status', 'APPROVED')->whereNotNull('buyer_office_id')->orderBy('req_date')->orderBy('req_no')->get();
        $left = $this->leftOf($indents->pluck('id'));
        $indents = $indents->filter(fn ($i) => ($left[$i->id] ?? collect())->sum('left') > 0)->values();
        $offices = BuyerOffice::query()->whereIn('id', $indents->pluck('buyer_office_id'))->orderBy('name')->get(['id', 'name']);
        $orders = Order::query()->whereIn('id', $indents->pluck('order_id'))->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no'])->keyBy('id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $indents->pluck('supplier_id')->filter())->pluck('name', 'id');
        $buyer = (string) $request->query('buyer', '');

        return ApiResponse::ok([
            'buyers' => $offices->map(fn ($o): array => ['value' => $o->id, 'label' => $o->name, 'count' => $indents->where('buyer_office_id', $o->id)->count()])->values()->all(),
            'requisitions' => $indents->where('buyer_office_id', $buyer)->values()->map(function (PurchasedFabricIndent $r) use ($left, $orders, $suppliers): array {
                $o = $orders[$r->order_id] ?? null;
                $mine = $left[$r->id] ?? collect();

                return [
                    'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'addition_for' => null, 'order_no' => $o?->order_no,
                    'job_no' => $o ? ($o->job_no ?: ($o->work_style_no ?: $o->style_no)) : null, 'supplier' => $r->supplier_id ? ($suppliers[$r->supplier_id] ?? null) : null,
                    'items' => $mine->where('left', '>', 0)->pluck('line.description')->filter()->implode(', '), 'left' => round((float) $mine->sum('left'), 4),
                ];
            })->all(),
        ]);
    }

    /** What an approved indent has left open, as lines for the order. */
    public function fromIndent(string $indent): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $req = PurchasedFabricIndent::query()->where('req_no', $indent)->where('status', 'APPROVED')->firstOrFail();
        $left = ($this->leftOf(collect([$req->id]))[$req->id] ?? collect())->where('left', '>', 0);
        $suppliers = $left->pluck('line.supplier_id')->filter()->unique();

        return ApiResponse::ok([
            'indent_id' => $req->id, 'req_no' => $req->req_no, 'supplier_id' => $req->supplier_id ?? ($suppliers->count() === 1 ? $suppliers->first() : null),
            'delivery_date' => $left->pluck('line.required_date')->filter()->min()?->toDateString(),
            'lines' => $left->map(fn (array $x): array => $this->lineFrom($req, $x['line'], $x['left']))->values()->all(),
        ]);
    }

    public function show(string $order): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->present($this->find($order)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data): PurchasedFabricPo {
            $row = PurchasedFabricPo::query()->create($this->header($data) + ['po_no' => $this->nextNumber($data['po_date']), 'revision' => 0, 'receive_status' => 'PENDING', 'created_by' => $this->caller()->auditUserId()]);
            $this->write($row, $data);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $order): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($order);
        $this->assertEditable($row);
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update($this->header($data) + ['revision' => $row->revision + 1]);
            $this->write($row, $data);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $order): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($order);
        $this->assertEditable($row);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    private function find(string $order): PurchasedFabricPo
    {
        return PurchasedFabricPo::query()->where('po_no', $order)->firstOrFail();
    }

    private function assertEditable(PurchasedFabricPo $row): void
    {
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_po.locked')]);
        }
        if ($this->hasReceipt($row)) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_po.has_receipt')]);
        }
    }

    /** A receipt, even a draft, names the order's lines — they are no longer rewritten. */
    private function hasReceipt(PurchasedFabricPo $row): bool
    {
        return PurchasedFabricMrrLine::query()->whereIn('po_line_id', PurchasedFabricPoLine::query()->where('po_id', $row->id)->select('id'))->exists();
    }

    /** @return array<string, mixed> */
    private function lineFrom(PurchasedFabricIndent $req, PurchasedFabricIndentLine $l, float $left): array
    {
        return [
            'indent_id' => $req->id, 'indent_line_id' => $l->id, 'req_no' => $req->req_no, 'fabric_type_id' => $l->fabric_type_id, 'description' => $l->description,
            'construction' => $l->construction, 'composition_id' => $l->composition_id, 'colour_id' => $l->colour_id, 'gsm' => $l->gsm, 'dia' => $l->dia, 'uom' => $l->uom,
            'pr_qty' => $left, 'qty' => $left, 'rate' => null, 'remarks' => $l->remarks,
        ];
    }

    /**
     * Each indent's lines with what is left open: requisitioned, less what every order but `$except` already orders.
     *
     * @return Collection<string, Collection<int, array{line: PurchasedFabricIndentLine, left: float}>>
     */
    private function leftOf(Collection $indentIds, ?string $except = null): Collection
    {
        $lines = PurchasedFabricIndentLine::query()->whereIn('indent_id', $indentIds)->orderBy('sort_order')->get();
        $ordered = PurchasedFabricPoLine::query()->whereIn('indent_line_id', $lines->pluck('id'))->when($except, fn ($q) => $q->where('po_id', '!=', $except))
            ->selectRaw('indent_line_id, sum(qty) as qty')->groupBy('indent_line_id')->pluck('qty', 'indent_line_id');

        return $lines->map(fn ($l): array => ['line' => $l, 'left' => round(max(0, $l->qty - (float) ($ordered[$l->id] ?? 0)), 4)])->groupBy(fn (array $x) => $x['line']->indent_id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?PurchasedFabricPo $row): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $required = ['supplier_id', 'po_date', 'source', 'payment_method', 'currency', 'payment_term', 'mode_of_shipment', 'delivery_date'];
        $data = $request->validate([
            'supplier_id' => ['required', $in('mp_suppliers')],
            'po_date' => ['required', 'date'],
            'source' => ['required', Rule::in(AccPurchaseOrder::SOURCES)],
            'indent_id' => ['nullable', Rule::exists('merch_pfab_indents', 'id')->where('company_id', $company)->where('status', 'APPROVED')],
            'beneficiary_company_id' => ['nullable', Rule::in([$company])],
            'billed_to_company_id' => ['nullable', Rule::in([$company])],
            'delivery_store_id' => ['nullable', $in('stores')],
            'is_online' => ['boolean'],
            'same_as_company' => ['boolean'],
            'po_group' => ['nullable', 'string', 'max:60'],
            'inco_term' => ['nullable', Rule::in(AccPurchaseOrder::INCO_TERMS)],
            'place_of_delivery' => ['nullable', 'string', 'max:160'],
            'payment_method' => ['required', Rule::in(AccPurchaseOrder::PAYMENT_METHODS)],
            'currency' => ['required', Rule::in(AccPurchaseOrder::CURRENCIES)],
            'conv_rate' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'payment_term' => ['required', Rule::in(AccPurchaseOrder::PAYMENT_TERMS)],
            'mode_of_shipment' => ['required', Rule::in(AccPurchaseOrder::SHIPMENT_MODES)],
            'delivery_date' => ['required', 'date', 'after_or_equal:po_date'],
            'cash_supplier' => ['nullable', 'string', 'max:120'],
            'other_term' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'vat_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tds_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vds_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'adjustment' => ['nullable', 'numeric', 'min:-9999999999', 'max:9999999999'],
            'adjustment_note' => ['nullable', 'string', 'max:200'],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.indent_line_id' => ['nullable', $in('merch_pfab_indent_lines')],
            'lines.*.qty' => ['nullable', 'numeric', 'max:999999999'],
            'lines.*.rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.remarks' => ['nullable', 'string', 'max:200'],
            'costs' => ['present', 'array', 'max:50'],
            'costs.*.cost_head_id' => ['nullable', $in('merch_proc_cost_heads')],
            'costs.*.amount' => ['nullable', 'numeric', 'max:9999999999'],
            'costs.*.remarks' => ['nullable', 'string', 'max:200'],
        ], collect($required)->mapWithKeys(fn (string $f) => ["{$f}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.apo.f.{$f}")])])->all() + [
            'indent_id.exists' => __('merchandise.acc_po.not_approved'),
            'delivery_date.after_or_equal' => __('merchandise.acc_po.delivery_before'),
            'conv_rate.gt' => __('merchandise.acc_po.conv_rate'),
        ]);
        // validate() hands the rows back in rule order — a row missing an early key moves last; keep the order they came in.
        ksort($data['lines']);
        ksort($data['costs']);

        if ($data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => __('merchandise.acc_po.lines_required')]);
        }
        $indentLines = PurchasedFabricIndentLine::query()->whereIn('id', collect($data['lines'])->pluck('indent_line_id')->filter())->get()->keyBy('id');
        $left = $this->leftOf($indentLines->pluck('indent_id')->unique(), $row?->id)->flatten(1)->keyBy(fn (array $x) => $x['line']->id);
        $reqs = PurchasedFabricIndent::query()->whereIn('id', $indentLines->pluck('indent_id'))->get(['id', 'req_no', 'status'])->keyBy('id');
        $asked = [];
        $errors = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            $lineId = $l['indent_line_id'] ?? null;
            if (! $lineId) {
                $errors["lines.{$i}.indent_line_id"] = __('merchandise.fpo.row_pr', ['n' => $n]);

                continue;
            }
            if (! isset($l['qty']) || (float) $l['qty'] <= 0) {
                $errors["lines.{$i}.qty"] = __('merchandise.acc_po.row_qty', ['n' => $n]);

                continue;
            }
            $req = $reqs[$indentLines[$lineId]->indent_id];
            if ($req->status !== 'APPROVED') {
                $errors["lines.{$i}.indent_line_id"] = __('merchandise.acc_po.row_not_approved', ['n' => $n, 'no' => $req->req_no]);

                continue;
            }
            $asked[$lineId] = ($asked[$lineId] ?? 0) + (float) $l['qty'];
            $available = (float) ($left[$lineId]['left'] ?? 0);
            if ($asked[$lineId] > $available + 0.00005) {
                $errors["lines.{$i}.qty"] = __('merchandise.acc_po.row_over', ['n' => $n, 'left' => rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.'), 'no' => $req->req_no]);
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
        $same = (bool) ($data['same_as_company'] ?? false);

        return [
            'po_date' => $data['po_date'], 'supplier_id' => $data['supplier_id'], 'source' => $data['source'], 'indent_id' => $data['indent_id'] ?? null,
            'beneficiary_company_id' => $data['beneficiary_company_id'] ?? null,
            // "Same as Company": the beneficiary is billed, not a separate company.
            'billed_to_company_id' => $same ? ($data['beneficiary_company_id'] ?? null) : ($data['billed_to_company_id'] ?? null),
            'delivery_store_id' => $data['delivery_store_id'] ?? null, 'is_online' => (bool) ($data['is_online'] ?? false), 'same_as_company' => $same, 'po_group' => $blank('po_group'),
            'inco_term' => $data['inco_term'] ?? null, 'place_of_delivery' => $blank('place_of_delivery'), 'payment_method' => $data['payment_method'], 'currency' => $data['currency'],
            'conv_rate' => $data['currency'] === 'BDT' ? 1 : (float) ($data['conv_rate'] ?? 1), 'payment_term' => $data['payment_term'], 'mode_of_shipment' => $data['mode_of_shipment'],
            'delivery_date' => $data['delivery_date'], 'cash_supplier' => $blank('cash_supplier'), 'other_term' => $blank('other_term'), 'remarks' => $blank('remarks'),
            'discount_pct' => (float) ($data['discount_pct'] ?? 0), 'discount_amount' => (float) ($data['discount_amount'] ?? 0), 'vat_pct' => (float) ($data['vat_pct'] ?? 0),
            'tds_pct' => (float) ($data['tds_pct'] ?? 0), 'vds_pct' => (float) ($data['vds_pct'] ?? 0), 'adjustment' => round((float) ($data['adjustment'] ?? 0), 2), 'adjustment_note' => $blank('adjustment_note'),
        ];
    }

    /** The lines (each carrying its indent line's fabric) and costs as sent, and the PO amount recomputed from them. */
    private function write(PurchasedFabricPo $row, array $data): void
    {
        PurchasedFabricPoLine::query()->where('po_id', $row->id)->delete();
        PurchasedFabricPoCost::query()->where('po_id', $row->id)->delete();
        $source = PurchasedFabricIndentLine::query()->whereIn('id', collect($data['lines'])->pluck('indent_line_id'))->get()->keyBy('id');
        $heads = ProcCostHead::query()->whereIn('id', collect($data['costs'])->pluck('cost_head_id'))->get()->keyBy('id');
        $value = 0.0;
        foreach (array_values($data['lines']) as $i => $l) {
            $src = $source[$l['indent_line_id']];
            $amount = round((float) $l['qty'] * (float) ($l['rate'] ?? 0), 2);
            $value += $amount;
            PurchasedFabricPoLine::query()->create([
                'po_id' => $row->id, 'indent_id' => $src->indent_id, 'indent_line_id' => $src->id, 'fabric_type_id' => $src->fabric_type_id, 'description' => $src->description,
                'construction' => $src->construction, 'composition_id' => $src->composition_id, 'colour_id' => $src->colour_id, 'gsm' => $src->gsm, 'dia' => $src->dia, 'uom' => $src->uom,
                'qty' => round((float) $l['qty'], 4), 'rate' => round((float) ($l['rate'] ?? 0), 4), 'amount' => $amount, 'remarks' => trim((string) ($l['remarks'] ?? '')) ?: null, 'sort_order' => $i + 1,
            ]);
        }
        $other = 0.0;
        $vatCosts = 0.0;
        foreach (array_values($data['costs']) as $i => $c) {
            $head = $heads[$c['cost_head_id']];
            $amount = round((float) $c['amount'], 2);
            $other += $amount;
            $vatCosts += $head->vat_applicable ? $amount : 0;
            PurchasedFabricPoCost::query()->create(['po_id' => $row->id, 'cost_head_id' => $head->id, 'vat_applicable' => $head->vat_applicable, 'amount' => $amount, 'remarks' => trim((string) ($c['remarks'] ?? '')) ?: null, 'sort_order' => $i + 1]);
        }
        $value = round($value, 2);
        $discount = round($row->discount_pct > 0 ? $value * $row->discount_pct / 100 : min($row->discount_amount, $value), 2);
        $vatBase = $value - $discount + $vatCosts;
        $vat = round($vatBase * $row->vat_pct / 100, 2);
        // TDS on goods less discount plus other costs, VAT excluded; VDS on the VAT base, never more than the VAT itself.
        $tds = round(($value - $discount + $other) * $row->tds_pct / 100, 2);
        $vds = round(min($vatBase * $row->vds_pct / 100, $vat), 2);
        $row->update([
            'po_value' => $value, 'other_cost' => round($other, 2), 'vat' => $vat, 'tds' => $tds, 'vds' => $vds,
            'net_amount' => round($value - $discount + $other + $vat - $tds - $vds + $row->adjustment, 2),
        ]);
    }

    /** @return array<string, mixed> */
    private function present(PurchasedFabricPo $row): array
    {
        $lines = PurchasedFabricPoLine::query()->where('po_id', $row->id)->orderBy('sort_order')->get();
        $costs = PurchasedFabricPoCost::query()->where('po_id', $row->id)->orderBy('sort_order')->get();
        $reqs = PurchasedFabricIndent::query()->whereIn('id', $lines->pluck('indent_id')->push($row->indent_id)->filter()->unique())->pluck('req_no', 'id');
        $left = $this->leftOf($lines->pluck('indent_id')->unique(), $row->id)->flatten(1)->keyBy(fn (array $x) => $x['line']->id);

        return $row->only([
            'po_no', 'supplier_id', 'source', 'indent_id', 'beneficiary_company_id', 'billed_to_company_id', 'delivery_store_id', 'is_online', 'same_as_company', 'po_group',
            'inco_term', 'place_of_delivery', 'payment_method', 'currency', 'conv_rate', 'payment_term', 'mode_of_shipment', 'cash_supplier', 'other_term', 'remarks', 'discount_pct',
            'discount_amount', 'vat_pct', 'tds_pct', 'vds_pct', 'adjustment', 'adjustment_note', 'po_value', 'other_cost', 'vat', 'tds', 'vds', 'net_amount', 'revision', 'receive_status',
        ]) + [
            'po_date' => $row->po_date->toDateString(), 'delivery_date' => $row->delivery_date->toDateString(), 'editable' => $row->isEditable() && ! $this->hasReceipt($row),
            'pr_numbers' => $reqs->values()->implode(', ') ?: null,
            'lines' => $lines->map(fn (PurchasedFabricPoLine $l): array => $l->only(['indent_id', 'indent_line_id', 'fabric_type_id', 'description', 'construction', 'composition_id', 'colour_id', 'gsm', 'dia', 'uom', 'qty', 'rate', 'amount', 'remarks']) + [
                'req_no' => $reqs[$l->indent_id] ?? null, 'pr_qty' => (float) ($left[$l->indent_line_id]['left'] ?? 0),
            ])->values()->all(),
            'costs' => $costs->map(fn (PurchasedFabricPoCost $c): array => $c->only(['cost_head_id', 'vat_applicable', 'amount', 'remarks']))->values()->all(),
        ];
    }

    /** PO-FAB-2026100001: the year and month, then the company's next fabric order that month; held to the end of the save. */
    private function nextNumber(string $date): string
    {
        $head = 'PO-FAB-'.CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_pfab_pos:'.$head.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) PurchasedFabricPo::query()->where('po_no', 'like', $head.'%')->pluck('po_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
