<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Controllers\Api;

use App\Modules\Inventory\Support\UomCodes;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\Merchandise\Models\AccReceipt;
use App\Modules\Merchandise\Models\AccReceiptLine;
use App\Modules\Merchandise\Models\AccRequisition;
use App\Modules\Merchandise\Models\AccRequisitionLine;
use App\Modules\Merchandise\Models\AccReturn;
use App\Modules\Merchandise\Models\AccReturnLine;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\BuyerOffice;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Merchandise\Models\StyleBom;
use App\Modules\Merchandise\Models\StyleBomLine;
use App\Modules\Merchandise\Models\StyleBomVersion;
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
 * Trims & Accessories → Accessories Purchase Indent (AnnotechERP 06/06), as
 * Brickland works it: an order picked off the order list fills the sheet
 * from its approved BOM — each accessory's required quantity for the order
 * (per garment, with its allowance and the BOM's additional %, over the
 * conversion), what is in stock and what is short — and the header and its
 * items are saved together, numbered PRQ-ACC-202610 0001. Draft →
 * Submitted → Approved or Rejected; only a draft or a rejected requisition
 * is changed, and a rejected one is reopened before it goes again. An item
 * already requisitioned for the order is refused unless the requisition
 * says what it is in addition for. Stock is re-read on every save: what the
 * finalised accessories receipts put in store.
 */
class AccRequisitionApiController extends ApiController
{
    /** The orders an indent is raised against. */
    private const OPEN = ['CONFIRMED', 'IN_PRODUCTION'];

    /** `?dummy=1`: the Dummy Order Accessories Indent — any order not cancelled, and its own requisitions. */
    public function options(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $dummy = $request->boolean('dummy');
        $orders = Order::query()->when($dummy, fn ($q) => $q->where('status', '!=', 'CANCELLED'), fn ($q) => $q->whereIn('status', self::OPEN))->orderBy('ship_date')->orderBy('order_no')->get();
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id')->filter())->get()->keyBy('id');
        $boms = $this->approvedBoms($styles->keys());
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->merge($styles->pluck('buyer_id'))->filter())->get(['id', 'name', 'short_name'])->keyBy('id');

        return ApiResponse::ok([
            'orders' => $orders->map(function (Order $o) use ($styles, $boms, $buyers): array {
                $s = $o->style_id ? ($styles[$o->style_id] ?? null) : null;
                $b = $buyers[$o->buyer_id ?? $s?->buyer_id] ?? null;

                return [
                    'value' => $o->id, 'order_no' => $o->order_no, 'label' => "{$o->order_no} · ".($s?->styleNo() ?? $o->style_no).' ('.($o->work_style_no ?: $o->style_no).')',
                    'job' => $o->job_no ?: ($o->work_style_no ?: $o->style_no), 'style' => $s?->styleNo() ?? $o->style_no, 'style_desc' => $s?->description,
                    'buyer' => $b ? ($b->short_name ?: $b->name) : null, 'buyer_office_id' => $o->buyer_office_id ?? $s?->buyer_office_id, 'order_type' => $o->order_type,
                    'qty' => (float) $o->total_qty, 'unit' => $o->unit, 'ship_date' => $o->ship_date?->toDateString(),
                    'bom' => $o->style_id && isset($boms[$o->style_id]) ? $boms[$o->style_id]['label'] : null,
                ];
            })->values()->all(),
            'offices' => BuyerOffice::query()->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($o): array => ['value' => $o->id, 'label' => $o->name])->values()->all(),
            'suppliers' => MaterialSupplier::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'code'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->code ? "{$s->name} ({$s->code})" : $s->name])->values()->all(),
            'items' => MaterialItem::query()->where('kind', 'TRIMS')->where('active', true)->orderBy('name')->get(['id', 'name', 'item_code', 'uom'])
                ->map(fn ($i): array => ['value' => $i->id, 'label' => $i->item_code ? "{$i->name} ({$i->item_code})" : $i->name, 'code' => $i->item_code, 'uom' => $i->uom, 'name' => $i->name])->values()->all(),
            'requisitions' => AccRequisition::query()->where('dummy', $dummy)->orderByDesc('req_no')->get(['req_no', 'order_id', 'status'])->map(fn ($r): array => ['value' => $r->req_no, 'label' => $r->req_no, 'order_id' => $r->order_id])->values()->all(),
            'statuses' => AccRequisition::STATUSES,
        ]);
    }

    /** Records: every requisition with its order, supplier and items. */
    public function index(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $rows = AccRequisition::query()->where('dummy', $request->boolean('dummy'))->orderByDesc('req_date')->orderByDesc('req_no')->get();
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id'))->get(['id', 'order_no', 'job_no', 'style_no', 'work_style_no'])->keyBy('id');
        $offices = BuyerOffice::query()->whereIn('id', $rows->pluck('buyer_office_id')->filter())->pluck('name', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('supplier_id')->filter())->pluck('name', 'id');
        $lines = AccRequisitionLine::query()->whereIn('requisition_id', $rows->pluck('id'))->orderBy('sort_order')->get(['requisition_id', 'description', 'required_qty'])->groupBy('requisition_id');

        return ApiResponse::ok($rows->map(function (AccRequisition $r) use ($orders, $offices, $suppliers, $lines): array {
            $o = $orders[$r->order_id] ?? null;

            return [
                'req_no' => $r->req_no, 'req_date' => $r->req_date->toDateString(), 'addition_for' => $r->addition_for,
                'buyer_ac' => $r->buyer_office_id ? ($offices[$r->buyer_office_id] ?? null) : null, 'job_no' => $o ? ($o->job_no ?: ($o->work_style_no ?: $o->style_no)) : null,
                'order_no' => $o?->order_no, 'supplier' => $r->supplier_id ? ($suppliers[$r->supplier_id] ?? null) : null,
                'items' => ($lines[$r->id] ?? collect())->pluck('description')->implode(', '), 'qty' => round((float) ($lines[$r->id] ?? collect())->sum('required_qty'), 3),
                'status' => $r->status, 'editable' => $r->isEditable(), 'req_month' => $r->req_date->format('Y-m'),
            ];
        })->values()->all());
    }

    /** The sheet an order fills from its approved BOM: each accessory, required for the order, in stock and short. */
    public function bom(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.view');
        $data = $request->validate(['order_id' => ['required', Rule::exists('merch_orders', 'id')->where('company_id', $this->caller()->companyId)]]);
        $order = Order::query()->whereKey($data['order_id'])->firstOrFail();
        $bom = $order->style_id ? ($this->approvedBoms(collect([$order->style_id]))[$order->style_id] ?? null) : null;
        if ($bom === null) {
            return ApiResponse::ok(['bom' => null, 'bom_version_id' => null, 'lines' => []]);
        }
        $items = MaterialItem::query()->where('kind', 'TRIMS')->where('active', true)->get(['id', 'name', 'item_code', 'uom']);
        $suppliers = MaterialSupplier::query()->where('active', true)->get(['id', 'name', 'short_name', 'code']);
        $version = $bom['version'];
        $lines = StyleBomLine::query()->where('version_id', $version->id)->where('category', '!=', 'MAIN_FABRIC')->orderBy('sort_order')->get();

        return ApiResponse::ok([
            'bom' => $bom['label'], 'bom_version_id' => $version->id,
            'lines' => $lines->map(function (StyleBomLine $l) use ($order, $version, $items, $suppliers): array {
                $item = $items->first(fn ($i) => ($l->item_code && strcasecmp((string) $i->item_code, $l->item_code) === 0) || strcasecmp((string) $i->name, (string) $l->item_name) === 0);
                $supplier = $l->supplier ? $suppliers->first(fn ($s) => in_array(mb_strtolower(trim($l->supplier)), array_filter([mb_strtolower((string) $s->name), mb_strtolower((string) $s->short_name), mb_strtolower((string) $s->code)]), true)) : null;
                $required = $this->required($l, (float) $order->total_qty, (float) ($version->addl_pct ?? 0));
                $stock = $this->stockOf($item?->id);

                return [
                    'bom_line_id' => $l->id, 'item_id' => $item?->id, 'item_code' => $item?->item_code ?? $l->item_code, 'description' => $l->item_name,
                    'uom' => UomCodes::normalize($item?->uom ?: (string) ($l->purchase_unit ?: $l->cons_unit)), 'required_qty' => $required, 'stock_qty' => $stock,
                    'short_qty' => round(max(0, $required - $stock), 3), 'required_date' => null, 'supplier_id' => $supplier?->id, 'remarks' => null,
                    'rate' => (float) $l->rate, 'bom_remarks' => $l->remarks,
                ];
            })->values()->all(),
        ]);
    }

    public function show(string $requisition): JsonResponse
    {
        $this->allow('merchandise.accessories.view');

        return ApiResponse::ok($this->present($this->find($requisition)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $data = $this->validated($request, null);
        $row = DB::transaction(function () use ($data, $request): AccRequisition {
            $dummy = $request->boolean('dummy');
            $row = AccRequisition::query()->create($this->header($data) + ['req_no' => $this->nextNumber($data['req_date'], $dummy), 'dummy' => $dummy, 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId()]);
            $this->writeLines($row, $data['lines'], $data['supplier_id'] ?? null);

            return $row;
        });

        return ApiResponse::created($this->present($row));
    }

    public function update(Request $request, string $requisition): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($requisition);
        $this->assertEditable($row);
        $data = $this->validated($request, $row);
        DB::transaction(function () use ($row, $data): void {
            $row->update($this->header($data));
            $this->writeLines($row, $data['lines'], $data['supplier_id'] ?? null);
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    public function destroy(string $requisition): JsonResponse
    {
        $this->allow('merchandise.accessories.manage');
        $row = $this->find($requisition);
        $this->assertEditable($row);
        $row->delete();

        return ApiResponse::ok(['deleted' => true]);
    }

    /** Submit, approve, reject or reopen: each only from the state it follows. */
    public function transition(string $requisition, string $action): JsonResponse
    {
        $row = $this->find($requisition);
        [$permission, $from, $to] = match ($action) {
            'submit' => ['merchandise.accessories.manage', ['DRAFT'], 'SUBMITTED'],
            'approve' => ['merchandise.accessories.approve', ['SUBMITTED'], 'APPROVED'],
            'reject' => ['merchandise.accessories.approve', ['SUBMITTED'], 'REJECTED'],
            'reopen' => ['merchandise.accessories.manage', ['REJECTED'], 'DRAFT'],
        };
        $this->allow($permission);
        if (! in_array($row->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_requisition.cannot', ['action' => __("merchandise.acc_requisition.action.{$action}"), 'status' => __('merchandise.master.apr.status.'.$row->status)])]);
        }
        if ($action === 'submit' && ! AccRequisitionLine::query()->where('requisition_id', $row->id)->exists()) {
            throw ValidationException::withMessages(['lines' => __('merchandise.acc_requisition.lines_required')]);
        }
        $row->update(['status' => $to] + match ($action) {
            'submit' => ['submitted_at' => now()],
            'approve', 'reject' => ['decided_at' => now(), 'decided_by' => $this->caller()->auditUserId()],
            default => ['decided_at' => null, 'decided_by' => null],
        });

        return ApiResponse::ok($this->present($row->refresh()));
    }

    private function find(string $requisition): AccRequisition
    {
        return AccRequisition::query()->where('req_no', $requisition)->firstOrFail();
    }

    private function assertEditable(AccRequisition $row): void
    {
        if (! $row->isEditable()) {
            throw ValidationException::withMessages(['status' => __('merchandise.acc_requisition.locked', ['status' => __('merchandise.master.apr.status.'.$row->status)])]);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AccRequisition $row): array
    {
        $company = $this->caller()->companyId;
        $in = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company);
        $data = $request->validate([
            'req_date' => ['required', 'date'],
            'order_id' => ['required', $in('merch_orders')],
            'buyer_office_id' => ['nullable', $in('buyer_offices')],
            'supplier_id' => ['nullable', $in('mp_suppliers')],
            'addition_for' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'lines' => ['present', 'array', 'max:300'],
            'lines.*.bom_line_id' => ['nullable', 'string'],
            'lines.*.item_id' => ['nullable', $in('mp_items')],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.uom' => ['nullable', 'string', 'max:16', UomCodes::rule($this->caller()->companyId)],
            'lines.*.required_qty' => ['nullable', 'numeric', 'max:999999999'],
            'lines.*.required_date' => ['nullable', 'date'],
            'lines.*.supplier_id' => ['nullable', $in('mp_suppliers')],
            'lines.*.remarks' => ['nullable', 'string', 'max:200'],
        ], ['req_date.required' => __('merchandise.acc_requisition.date_required'), 'order_id.required' => __('merchandise.acc_requisition.order_required')]);

        // validate() hands the rows back in rule order — a row missing an early key moves last; keep the order they came in.
        ksort($data['lines']);

        if ($data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => __('merchandise.acc_requisition.lines_required')]);
        }
        $items = MaterialItem::query()->whereIn('id', collect($data['lines'])->pluck('item_id')->filter())->get(['id', 'name'])->keyBy('id');
        $errors = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $n = $i + 1;
            if (empty($l['item_id']) && trim((string) ($l['description'] ?? '')) === '') {
                $errors["lines.{$i}.description"] = __('merchandise.acc_requisition.row_item', ['n' => $n]);
            }
            if (! isset($l['required_qty']) || (float) $l['required_qty'] <= 0) {
                $errors["lines.{$i}.required_qty"] = __('merchandise.acc_requisition.row_qty', ['n' => $n]);
            }
        }
        // An item already on the order's regular requisition is refused, unless this one says what it is in addition for;
        // an addition never blocks the regular requisition.
        if ($errors === [] && trim((string) ($data['addition_for'] ?? '')) === '') {
            // Regular and dummy-order indents are kept apart.
            $others = AccRequisition::query()->where('order_id', $data['order_id'])->where('dummy', $row ? $row->dummy : $request->boolean('dummy'))->where('status', '!=', 'REJECTED')->where(fn ($q) => $q->whereNull('addition_for')->orWhere('addition_for', ''))
                ->when($row, fn ($q) => $q->whereKeyNot($row->id))->get(['id', 'req_no'])->keyBy('id');
            $taken = AccRequisitionLine::query()->whereIn('requisition_id', $others->keys())->get(['requisition_id', 'item_id', 'description']);
            foreach (array_values($data['lines']) as $i => $l) {
                $key = $this->lineKey($l['item_id'] ?? null, $l['description'] ?? null, $items);
                $hit = $taken->first(fn ($t) => $this->lineKey($t->item_id, $t->description, collect()) === $key || ($t->item_id && $t->item_id === ($l['item_id'] ?? null)));
                if ($hit) {
                    $errors["lines.{$i}.item_id"] = __('merchandise.acc_requisition.row_duplicate', ['n' => $i + 1, 'no' => $others[$hit->requisition_id]->req_no]);
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private function lineKey(?string $itemId, ?string $description, Collection $items): string
    {
        return $itemId ? "item:{$itemId}" : 'text:'.mb_strtolower(trim((string) $description));
    }

    /** @return array<string, mixed> */
    private function header(array $data): array
    {
        $order = Order::query()->whereKey($data['order_id'])->first(['style_id', 'buyer_office_id']);
        $style = $order?->style_id ? Style::query()->whereKey($order->style_id)->first(['buyer_office_id']) : null;
        $bom = $order?->style_id ? ($this->approvedBoms(collect([$order->style_id]))[$order->style_id] ?? null) : null;
        $blank = fn (string $k): ?string => isset($data[$k]) && trim((string) $data[$k]) !== '' ? trim((string) $data[$k]) : null;

        return [
            'req_date' => $data['req_date'], 'order_id' => $data['order_id'],
            // The buyer account follows the order (or its style) when none is picked.
            'buyer_office_id' => ($data['buyer_office_id'] ?? null) ?: ($order?->buyer_office_id ?? $style?->buyer_office_id),
            'supplier_id' => $data['supplier_id'] ?? null, 'addition_for' => $blank('addition_for'), 'remarks' => $blank('remarks'), 'bom_version_id' => $bom['version']->id ?? null,
        ];
    }

    /** The lines as sent — the header's supplier on those naming none — stock re-read and the short quantity recomputed. */
    private function writeLines(AccRequisition $row, array $lines, ?string $supplierId): void
    {
        AccRequisitionLine::query()->where('requisition_id', $row->id)->delete();
        $items = MaterialItem::query()->whereIn('id', collect($lines)->pluck('item_id')->filter())->get(['id', 'name', 'item_code', 'uom'])->keyBy('id');
        foreach (array_values($lines) as $i => $l) {
            $item = isset($l['item_id']) ? ($items[$l['item_id']] ?? null) : null;
            $required = round((float) $l['required_qty'], 3);
            $stock = $this->stockOf($item?->id);
            AccRequisitionLine::query()->create([
                'requisition_id' => $row->id, 'bom_line_id' => $l['bom_line_id'] ?? null, 'item_id' => $item?->id, 'item_code' => $item?->item_code,
                'description' => trim((string) ($l['description'] ?? '')) ?: (string) $item?->name, 'uom' => UomCodes::normalize($l['uom'] ?? null) ?? UomCodes::normalize($item?->uom),
                'required_qty' => $required, 'stock_qty' => $stock, 'short_qty' => round(max(0, $required - $stock), 3), 'required_date' => $l['required_date'] ?? null,
                'supplier_id' => ($l['supplier_id'] ?? null) ?: $supplierId, 'remarks' => trim((string) ($l['remarks'] ?? '')) ?: null, 'sort_order' => $i + 1,
            ]);
        }
    }

    /** What the order needs of a BOM line, in its purchase unit — the BOM screen's own reckoning. */
    private function required(StyleBomLine $l, float $orderQty, float $addlPct): float
    {
        $perGarment = (float) $l->consumption / ((float) $l->per_gmt ?: 1);
        $extra = 1 + (float) ($l->allow_pct ?? 0) / 100 + ($l->apply_addl ? $addlPct / 100 : 0);

        return round($orderQty * $perGarment * $extra / ((float) $l->conversion ?: 1), 3);
    }

    /** An item's stock: what finalised accessories receipts put in store, less what finalised return challans sent back (issues come with the trims store). */
    private function stockOf(?string $itemId): float
    {
        if ($itemId === null) {
            return 0.0;
        }
        $in = (float) AccReceiptLine::query()->where('item_id', $itemId)->whereIn('mrr_id', AccReceipt::query()->where('status', 'FINALIZED')->select('id'))->sum('qty');
        $back = (float) AccReturnLine::query()->where('item_id', $itemId)->whereIn('return_id', AccReturn::query()->where('status', 'FINALIZED')->select('id'))->sum('qty');

        return round($in - $back, 3);
    }

    /**
     * Each style's approved BOM: its latest approved version, named "BOM-000103 V1".
     *
     * @return array<string, array{version: StyleBomVersion, label: string}>
     */
    private function approvedBoms(Collection $styleIds): array
    {
        $boms = StyleBom::query()->whereIn('style_id', $styleIds)->get(['id', 'style_id', 'bom_no'])->keyBy('id');
        $versions = StyleBomVersion::query()->whereIn('bom_id', $boms->keys())->where('status', StyleBomVersion::APPROVED)->orderByDesc('version')->get();
        $out = [];
        foreach ($versions as $v) {
            $styleId = $boms[$v->bom_id]->style_id;
            $out[$styleId] ??= ['version' => $v, 'label' => "{$boms[$v->bom_id]->bom_no} V{$v->version}"];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function present(AccRequisition $row): array
    {
        $order = Order::query()->whereKey($row->order_id)->first();
        $bom = $row->bom_version_id ? StyleBomVersion::query()->whereKey($row->bom_version_id)->first() : null;
        $lines = AccRequisitionLine::query()->where('requisition_id', $row->id)->orderBy('sort_order')->get();

        return [
            'req_no' => $row->req_no, 'req_date' => $row->req_date->toDateString(), 'order_id' => $row->order_id, 'order_no' => $order?->order_no,
            'buyer_office_id' => $row->buyer_office_id, 'supplier_id' => $row->supplier_id, 'addition_for' => $row->addition_for, 'remarks' => $row->remarks,
            'bom' => $bom ? (StyleBom::query()->whereKey($bom->bom_id)->value('bom_no')." V{$bom->version}") : null, 'status' => $row->status, 'editable' => $row->isEditable(), 'dummy' => $row->dummy,
            'lines' => $lines->map(fn (AccRequisitionLine $l): array => [
                'id' => $l->id, 'bom_line_id' => $l->bom_line_id, 'item_id' => $l->item_id, 'item_code' => $l->item_code, 'description' => $l->description, 'uom' => $l->uom,
                'required_qty' => $l->required_qty, 'stock_qty' => $l->stock_qty, 'short_qty' => $l->short_qty, 'required_date' => $l->required_date?->toDateString(),
                'supplier_id' => $l->supplier_id, 'remarks' => $l->remarks,
            ])->values()->all(),
        ];
    }

    /** PRQ-ACC-2026100001 (a dummy-order indent PRQ-DUM-…): the year and month, then the company's next requisition that month; held to the end of the save. */
    private function nextNumber(string $date, bool $dummy = false): string
    {
        $head = ($dummy ? 'PRQ-DUM-' : 'PRQ-ACC-').CarbonImmutable::parse($date)->format('Ym');
        DB::select('select pg_advisory_xact_lock(hashtext(?))', ['merch_acc_requisitions:'.$head.':'.app(TenantContext::class)->companyId()]);
        $n = 1 + (int) AccRequisition::query()->where('req_no', 'like', $head.'%')->pluck('req_no')->map(fn (string $v): int => (int) substr($v, strlen($head)))->max();

        return $head.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
