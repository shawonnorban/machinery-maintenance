<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Inventory\Models\Store;
use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\AccPurchaseOrder;
use App\Modules\Merchandise\Models\AccPurchaseOrderLine;
use App\Modules\Merchandise\Models\AccReceiptLine;
use App\Modules\Merchandise\Models\AccRequisition;
use App\Modules\Merchandise\Models\AccRequisitionLine;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Order;
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
 * Trims & Accessories → Accessories Purchase Order (AnnotechERP 06/06), as
 * Brickland works it: a supplier's order with its master data (supplier,
 * source, the requisition it is against, beneficiary, billed-to and the store
 * it is delivered to), its terms & condition and its priced lines, saved
 * together and numbered PO-ACC-202610 0001. The Requisition List gives, buyer
 * by buyer, the approved requisitions still waiting to be ordered; one taken
 * from there fills the lines with what is left of it. A line never orders
 * more than its requisition line has left. Each change is a new revision;
 * an order received against is no longer changed here.
 */
class AccPurchaseOrderApiController extends ApiController
{
    public function options(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $company = Company::query()->whereKey($this->caller()->companyId)->first(['id', 'name']);

        return ApiResponse::ok([
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'items' => MaterialItem::query()->where('kind', 'TRIMS')->where('active', true)->orderBy('name')->get(['id', 'name', 'item_code', 'uom'])
                ->map(fn ($i): array => ['value' => $i->id, 'label' => $i->item_code ? "{$i->name} ({$i->item_code})" : $i->name, 'uom' => $i->uom])->values()->all(),
            'stores' => Store::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'companies' => $company ? [['value' => $company->id, 'label' => $company->name]] : [],
            'requisitions' => AccRequisition::query()->where('status', 'APPROVED')->orderByDesc('req_no')->get(['id', 'req_no'])->map(fn ($r): array => ['value' => $r->id, 'label' => $r->req_no])->values()->all(),
            'orders' => AccPurchaseOrder::query()->orderByDesc('po_no')->get(['po_no'])->map(fn ($p): array => ['value' => $p->po_no, 'label' => $p->po_no])->values()->all(),
            'sources' => AccPurchaseOrder::SOURCES, 'inco_terms' => AccPurchaseOrder::INCO_TERMS, 'payment_methods' => AccPurchaseOrder::PAYMENT_METHODS,
            'currencies' => AccPurchaseOrder::CURRENCIES, 'payment_terms' => AccPurchaseOrder::PAYMENT_TERMS, 'shipment_modes' => AccPurchaseOrder::SHIPMENT_MODES,
        ]);
    }

    /** Records: every order with its receive and revision status, requisitions, supplier, store, terms and value. */
    public function index(): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = AccPurchaseOrder::query()->orderByDesc('po_date')->orderByDesc('po_no')->get();
        $lines = AccPurchaseOrderLine::query()->whereIn('po_id', $rows->pluck('id'))->get(['po_id', 'requisition_id', 'qty', 'unit_price'])->groupBy('po_id');
        $reqs = AccRequisition::query()->whereIn('id', $rows->pluck('requisition_id')->merge($lines->flatten()->pluck('requisition_id'))->filter()->unique())->pluck('req_no', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id'))->pluck('name', 'id');
        $stores = Store::query()->whereIn('id', $rows->pluck('delivery_store_id')->filter())->pluck('name', 'id');

        return ApiResponse::ok($rows->map(function (AccPurchaseOrder $p) use ($lines, $reqs, $suppliers, $stores): array {
            $mine = $lines[$p->id] ?? collect();

            return [
                'po_no' => $p->po_no, 'po_date' => $p->po_date->toDateString(), 'receive_status' => $p->receive_status, 'revision' => $p->revision,
                'pr_numbers' => $this->prNumbers($p, $mine, $reqs), 'mrrs' => null, 'supplier' => $suppliers[$p->supplier_id] ?? null,
                'store' => $p->delivery_store_id ? ($stores[$p->delivery_store_id] ?? null) : null, 'source' => $p->source, 'inco_term' => $p->inco_term,
                'payment_term' => $p->payment_term, 'payment_method' => $p->payment_method, 'currency' => $p->currency,
                'value' => round((float) $mine->sum(fn ($l) => $l->qty * $l->unit_price), 2), 'editable' => $p->isEditable(),
            ];
        })->values()->all());
    }

    /** Requisition List: the buyers with approved requisitions still to order, and the picked buyer's (`?buyer=`) requisitions. */
    public function requisitions(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $reqs = AccRequisition::query()->where('status', 'APPROVED')->whereNotNull('buyer_office_id')->orderBy('req_date')->orderBy('req_no')->get();
        $left = $this->leftOf($reqs->pluck('id'));
        $reqs = $reqs->filter(fn (AccRequisition $r) => ($left[$r->id] ?? collect())->sum('left') > 0)->values();
        $offices = BuyerOffice::query()->whereIn('id', $reqs->pluck('buyer_office_id'))->orderBy('name')->get(['id', 'name']);
        $orders = Order::query()->whereIn('id', $reqs->pluck('order_id'))->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no'])->keyBy('id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $reqs->pluck('supplier_id')->filter())->pluck('name', 'id');
        $buyer = (string) $request->query('buyer', '');

        return ApiResponse::ok([
            'buyers' => $offices->map(fn ($o): array => ['value' => $o->id, 'label' => $o->name, 'count' => $reqs->where('buyer_office_id', $o->id)->count()])->values()->all(),
            'requisitions' => $reqs->where('buyer_office_id', $buyer)->values()->map(function (AccRequisition $r) use ($left, $orders, $suppliers): array {
                $o = $orders[$r->order_id] ?? null;
                $mine = $left[$r->id] ?? collect();

                return [
                    'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'buyer_office_id' => $r->buyer_office_id, 'addition_for' => $r->addition_for,
                    'order_no' => $o?->order_no, 'job_no' => $o ? ($o->job_no ?: ($o->work_style_no ?: $o->style_no)) : null,
                    'supplier' => $r->supplier_id ? ($suppliers[$r->supplier_id] ?? null) : null,
                    'items' => $mine->where('left', '>', 0)->pluck('line.description')->implode(', '), 'left' => round((float) $mine->sum('left'), 3),
                ];
            })->values()->all(),
        ]);
    }

    /** What an approved requisition has left to order, as lines for a new order. */
    public function fromRequisition(string $requisition): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $req = AccRequisition::query()->where('req_no', $requisition)->where('status', 'APPROVED')->firstOrFail();
        $left = ($this->leftOf(collect([$req->id]))[$req->id] ?? collect())->where('left', '>', 0);
        $suppliers = $left->pluck('line.supplier_id')->filter()->unique();

        return ApiResponse::ok([
            'requisition_id' => $req->id, 'req_no' => $req->req_no,
            // The requisition's supplier, else the one its lines share.
            'supplier_id' => $req->supplier_id ?? ($suppliers->count() === 1 ? $suppliers->first() : null),
            'delivery_date' => $left->pluck('line.required_date')->filter()->min()?->toDateString(),
            'lines' => $left->map(fn (array $x): array => [
                'item_id' => $x['line']->item_id, 'requisition_id' => $req->id, 'requisition_line_id' => $x['line']->id, 'req_no' => $req->req_no,
                'specification' => $x['line']->item_id ? null : $x['line']->description, 'uom' => $x['line']->uom, 'qty' => $x['left'], 'unit_price' => null, 'note' => $x['line']->remarks,
            ])->values()->all(),
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
        $row = DB::transaction(function () use ($data): AccPurchaseOrder {
            $row = AccPurchaseOrder::query()->create($this->header($data) + ['po_no' => $this->nextNumber($data['po_date']), 'revision' => 0, 'receive_status' => 'PENDING', 'created_by' => $this->caller()->auditUserId()]);
            $this->writeLines($row, $data['lines']);

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
            $this->writeLines($row, $data['lines']);
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

    private function find(string $order): AccPurchaseOrder
    {
        return AccPurchaseOrder::query()->where('po_no', $order)->firstOrFail();
    }

    private function assertEditable(AccPurchaseOrder $row): void
    {
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_po.locked')]);
        }
        if ($this->hasReceipt($row)) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_po.has_receipt')]);
        }
    }

    /** A receipt, even a draft, names the order's lines — they are no longer rewritten. */
    private function hasReceipt(AccPurchaseOrder $row): bool
    {
        return AccReceiptLine::query()->whereIn('po_line_id', AccPurchaseOrderLine::query()->where('po_id', $row->id)->select('id'))->exists();
    }

    /**
     * Each requisition's lines with what is left to order: required, less what other orders (all but `$except`) already order.
     *
     * @return Collection<string, Collection<int, array{line: AccRequisitionLine, left: float}>>
     */
    private function leftOf(Collection $requisitionIds, ?string $except = null): Collection
    {
        $lines = AccRequisitionLine::query()->whereIn('requisition_id', $requisitionIds)->orderBy('sort_order')->get();
        $ordered = AccPurchaseOrderLine::query()->whereIn('requisition_line_id', $lines->pluck('id'))->when($except, fn ($q) => $q->where('po_id', '!=', $except))
            ->selectRaw('requisition_line_id, sum(qty) as qty')->groupBy('requisition_line_id')->pluck('qty', 'requisition_line_id');

        return $lines->map(fn (AccRequisitionLine $l): array => ['line' => $l, 'left' => round(max(0, $l->required_qty - (float) ($ordered[$l->id] ?? 0)), 4)])->groupBy(fn (array $x) => $x['line']->requisition_id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AccPurchaseOrder $row): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $required = ['supplier_id', 'po_date', 'source', 'payment_method', 'currency', 'payment_term', 'mode_of_shipment', 'delivery_date'];
        $data = $request->validate([
            'supplier_id' => ['required', $in('mp_suppliers')],
            'po_date' => ['required', 'date'],
            'source' => ['required', Rule::in(AccPurchaseOrder::SOURCES)],
            'requisition_id' => ['nullable', Rule::exists('merch_acc_requisitions', 'id')->where('company_id', $company)->where('status', 'APPROVED')],
            'beneficiary_company_id' => ['nullable', Rule::in([$company])],
            'billed_to_company_id' => ['nullable', Rule::in([$company])],
            'delivery_store_id' => ['nullable', $in('stores')],
            'is_online' => ['boolean'],
            'same_as_company' => ['boolean'],
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
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.item_id' => ['nullable', $in('mp_items')],
            'lines.*.requisition_line_id' => ['nullable', Rule::exists('merch_acc_requisition_lines', 'id')->where('company_id', $company)],
            'lines.*.specification' => ['nullable', 'string', 'max:200'],
            'lines.*.uom' => ['nullable', 'string', 'max:24', UomCodes::rule($this->caller()->companyId)],
            'lines.*.qty' => ['nullable', 'numeric', 'max:999999999'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.note' => ['nullable', 'string', 'max:200'],
        ], collect($required)->mapWithKeys(fn (string $f) => ["{$f}.required" => __('merchandise.acc_po.required', ['field' => __("merchandise.master.apo.f.{$f}")])])->all() + [
            'requisition_id.exists' => __('merchandise.acc_po.not_approved'),
            'delivery_date.after_or_equal' => __('merchandise.acc_po.delivery_before'),
            'conv_rate.gt' => __('merchandise.acc_po.conv_rate'),
        ]);

        // validate() hands the rows back in rule order — a row missing an early key moves last; keep the order they came in.
        ksort($data['lines']);

        if ($data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => __('merchandise.acc_po.lines_required')]);
        }
        $reqLines = AccRequisitionLine::query()->whereIn('id', collect($data['lines'])->pluck('requisition_line_id')->filter())->get()->keyBy('id');
        $left = $this->leftOf($reqLines->pluck('requisition_id')->unique(), $row?->id)->flatten(1)->keyBy(fn (array $x) => $x['line']->id);
        $reqNos = AccRequisition::query()->whereIn('id', $reqLines->pluck('requisition_id'))->get(['id', 'req_no', 'status'])->keyBy('id');
        $asked = [];
        $errors = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            if (empty($l['item_id'])) {
                $errors["lines.{$i}.item_id"] = __('merchandise.acc_po.row_item', ['n' => $n]);
            }
            if (! isset($l['qty']) || (float) $l['qty'] <= 0) {
                $errors["lines.{$i}.qty"] = __('merchandise.acc_po.row_qty', ['n' => $n]);

                continue;
            }
            $lineId = $l['requisition_line_id'] ?? null;
            if ($lineId) {
                $req = $reqNos[$reqLines[$lineId]->requisition_id];
                if ($req->status !== 'APPROVED') {
                    $errors["lines.{$i}.requisition_line_id"] = __('merchandise.acc_po.row_not_approved', ['n' => $n, 'no' => $req->req_no]);

                    continue;
                }
                // The same requisition line on two rows of one order counts once, in full.
                $asked[$lineId] = ($asked[$lineId] ?? 0) + (float) $l['qty'];
                $available = (float) ($left[$lineId]['left'] ?? 0);
                if ($asked[$lineId] > $available + 0.00005) {
                    $errors["lines.{$i}.qty"] = __('merchandise.acc_po.row_over', ['n' => $n, 'left' => rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.'), 'no' => $req->req_no]);
                }
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
            'po_date' => $data['po_date'], 'supplier_id' => $data['supplier_id'], 'source' => $data['source'], 'requisition_id' => $data['requisition_id'] ?? null,
            'beneficiary_company_id' => $data['beneficiary_company_id'] ?? null,
            // "Same as Company": the beneficiary is billed, not a separate company.
            'billed_to_company_id' => $same ? ($data['beneficiary_company_id'] ?? null) : ($data['billed_to_company_id'] ?? null),
            'delivery_store_id' => $data['delivery_store_id'] ?? null, 'is_online' => (bool) ($data['is_online'] ?? false), 'same_as_company' => $same,
            'inco_term' => $data['inco_term'] ?? null, 'place_of_delivery' => $blank('place_of_delivery'), 'payment_method' => $data['payment_method'], 'currency' => $data['currency'],
            'conv_rate' => $data['currency'] === 'BDT' ? 1 : (float) ($data['conv_rate'] ?? 1), 'payment_term' => $data['payment_term'], 'mode_of_shipment' => $data['mode_of_shipment'],
            'delivery_date' => $data['delivery_date'], 'cash_supplier' => $blank('cash_supplier'), 'other_term' => $blank('other_term'), 'remarks' => $blank('remarks'),
        ];
    }

    private function writeLines(AccPurchaseOrder $row, array $lines): void
    {
        AccPurchaseOrderLine::query()->where('po_id', $row->id)->delete();
        $reqOf = AccRequisitionLine::query()->whereIn('id', collect($lines)->pluck('requisition_line_id')->filter())->pluck('requisition_id', 'id');
        $items = MaterialItem::query()->whereIn('id', collect($lines)->pluck('item_id'))->pluck('uom', 'id');
        foreach (array_values($lines) as $i => $l) {
            $lineId = $l['requisition_line_id'] ?? null;
            AccPurchaseOrderLine::query()->create([
                'po_id' => $row->id, 'item_id' => $l['item_id'], 'requisition_id' => $lineId ? $reqOf[$lineId] : null, 'requisition_line_id' => $lineId,
                'specification' => trim((string) ($l['specification'] ?? '')) ?: null, 'uom' => UomCodes::normalize($l['uom'] ?? null) ?? UomCodes::normalize($items[$l['item_id']] ?? null),
                'qty' => round((float) $l['qty'], 4), 'unit_price' => round((float) ($l['unit_price'] ?? 0), 4), 'note' => trim((string) ($l['note'] ?? '')) ?: null, 'sort_order' => $i + 1,
            ]);
        }
    }

    /** The requisitions an order is against: the header's, then its lines', each once. */
    private function prNumbers(AccPurchaseOrder $p, Collection $lines, Collection $reqs): ?string
    {
        $ids = collect([$p->requisition_id])->merge($lines->pluck('requisition_id'))->filter()->unique();

        return $ids->map(fn ($id) => $reqs[$id] ?? null)->filter()->implode(', ') ?: null;
    }

    /** @return array<string, mixed> */
    private function present(AccPurchaseOrder $row): array
    {
        $lines = AccPurchaseOrderLine::query()->where('po_id', $row->id)->orderBy('sort_order')->get();
        $reqs = AccRequisition::query()->whereIn('id', $lines->pluck('requisition_id')->push($row->requisition_id)->filter()->unique())->pluck('req_no', 'id');

        return [
            'po_no' => $row->po_no, 'po_date' => $row->po_date->toDateString(), 'supplier_id' => $row->supplier_id, 'source' => $row->source, 'requisition_id' => $row->requisition_id,
            'beneficiary_company_id' => $row->beneficiary_company_id, 'billed_to_company_id' => $row->billed_to_company_id, 'delivery_store_id' => $row->delivery_store_id,
            'is_online' => $row->is_online, 'same_as_company' => $row->same_as_company, 'inco_term' => $row->inco_term, 'place_of_delivery' => $row->place_of_delivery,
            'payment_method' => $row->payment_method, 'currency' => $row->currency, 'conv_rate' => $row->conv_rate, 'payment_term' => $row->payment_term,
            'mode_of_shipment' => $row->mode_of_shipment, 'delivery_date' => $row->delivery_date->toDateString(), 'cash_supplier' => $row->cash_supplier,
            'other_term' => $row->other_term, 'remarks' => $row->remarks, 'revision' => $row->revision, 'receive_status' => $row->receive_status, 'editable' => $row->isEditable() && ! $this->hasReceipt($row),
            'pr_numbers' => $this->prNumbers($row, $lines, $reqs), 'value' => round((float) $lines->sum(fn ($l) => $l->qty * $l->unit_price), 2),
            'lines' => $lines->map(fn (AccPurchaseOrderLine $l): array => [
                'item_id' => $l->item_id, 'requisition_id' => $l->requisition_id, 'requisition_line_id' => $l->requisition_line_id, 'req_no' => $l->requisition_id ? ($reqs[$l->requisition_id] ?? null) : null,
                'specification' => $l->specification, 'uom' => $l->uom, 'qty' => $l->qty, 'unit_price' => $l->unit_price, 'note' => $l->note,
            ])->values()->all(),
        ];
    }

    /** PO-ACC-2026100001: the year and month, then the company's next order that month; held to the end of the save. */
    private function nextNumber(string $date): string
    {
        $head = 'PO-ACC-'.CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_acc_pos:'.$head.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) AccPurchaseOrder::query()->where('po_no', 'like', $head.'%')->pluck('po_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
