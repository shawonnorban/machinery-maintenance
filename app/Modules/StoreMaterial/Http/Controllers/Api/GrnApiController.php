<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Http\Controllers\Api;

use App\Modules\HrPayroll\Models\HrEmployee;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\PurchaseRequisition;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Modules\StoreMaterial\Models\Grn;
use App\Modules\StoreMaterial\Models\GrnLine;
use App\Modules\StoreMaterial\Models\Roll;
use App\Modules\StoreMaterial\Models\Stock;
use App\Modules\StoreMaterial\Support\StoreBook;
use App\Shared\Files\Actions\StoreFileAttachment;
use App\Shared\Files\Models\FileAttachment;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Store & Material → In-House / GRN (AnnotechERP 09/01), as Brickland keeps
 * it. Against PR: a PR number fetches the approved requisition — buyer, PO,
 * style, order, supplier and every approved item with what is received and
 * pending — and the actual receive quantities make the GRN. Direct Receive:
 * material that arrived without a PR (opening stock, buyer-supplied goods,
 * samples, local purchases), each item from the Fabric / Accessories master,
 * for a buyer and style or for general stock. A fabric line may be received
 * roll by roll — roll number, dye lot, GSM and width — its rolls adding up to
 * the line. A confirmed GRN goes straight to stock and can only be reversed —
 * by the Store Manager, never deleted — and not once any of it, or any of its
 * rolls, has been issued.
 */
class GrnApiController extends ApiController
{
    public const CURRENCIES = ['BDT', 'USD'];

    /** What Direct Receive picks from, and the number the next GRN takes. */
    public function options(): JsonResponse
    {
        $this->allow('storematerial.view');

        return ApiResponse::ok([
            'next_no' => Procurement::previewNumber('GRN', 'sm_grns', 'grn_no', now()->toDateString()),
            'currencies' => self::CURRENCIES,
            'items' => MaterialItem::query()->where('active', true)->orderBy('kind')->orderBy('item_code')->get()
                ->map(fn (MaterialItem $i): array => ['value' => $i->id, 'label' => "{$i->item_code} — ".StoreBook::material($i), 'kind' => $i->kind, 'uom' => $i->uom])->values()->all(),
        ]);
    }

    /** Against PR: the approved requisition and its items, with what is received and pending. */
    public function requisition(string $pr): JsonResponse
    {
        $this->allow('storematerial.view');

        return ApiResponse::ok($this->prPayload($this->approvedPr($pr)));
    }

    /** Confirm In-House against a PR. */
    public function storeAgainstPr(Request $request): JsonResponse
    {
        $this->allow('storematerial.receive');
        $data = $request->validate([
            'pr_no' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:1'], 'lines.*.requisition_line_id' => ['required', 'string'], 'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rolls' => ['nullable', 'array', 'max:500'],
            'received_by' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $pr = $this->approvedPr($data['pr_no']);
        $receiver = StoreBook::employee($data['received_by'] ?? null, 'received_by');
        $wanted = collect($data['lines'])->filter(fn (array $l): bool => (float) ($l['qty'] ?? 0) > 0)->keyBy('requisition_line_id');
        if ($wanted->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('storematerial.grn.nothing')]);
        }
        $order = $pr->order_id ? Order::query()->find($pr->order_id) : null;
        $for = $this->orderFor($order);
        $grn = DB::transaction(function () use ($pr, $wanted, $receiver, $data, $order, $for): Grn {
            $lines = PurchaseRequisitionLine::query()->where('requisition_id', $pr->id)->whereIn('status', PurchaseRequisitionLine::DELIVERY)
                ->whereIn('id', $wanted->keys())->lockForUpdate()->orderBy('sort_order')->get();
            if ($lines->count() !== $wanted->count()) {
                throw ValidationException::withMessages(['lines' => __('storematerial.grn.nothing')]);
            }
            $received = $this->receivedOf($lines->pluck('id'));
            $errors = [];
            foreach ($lines as $l) {
                $pending = round((float) $l->req_qty - ($received[$l->id] ?? 0), 3);
                if ((float) $wanted[$l->id]['qty'] > $pending + 0.0005) {
                    $errors["lines.{$l->id}"] = __('storematerial.grn.over', ['item' => MaterialItem::query()->whereKey($l->item_id)->value('name'), 'pending' => StoreBook::qty($pending), 'uom' => $l->uom]);
                }
            }
            $seen = [];
            $rolls = [];
            foreach ($lines as $l) {
                $rolls[$l->id] = $this->rolls($wanted[$l->id]['rolls'] ?? [], (float) $wanted[$l->id]['qty'], $l->kind, $l->uom, $l->id, $errors, $seen);
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            $today = now()->toDateString();
            $grn = Grn::query()->create($for + [
                'grn_no' => Procurement::nextNumber('GRN', 'sm_grns', 'grn_no', $today), 'grn_date' => $today, 'kind' => 'PR', 'requisition_id' => $pr->id,
                'supplier_id' => $lines->first()->approved_supplier_id, 'received_by' => $receiver?->id, 'remarks' => $data['remarks'] ?? null,
                'status' => 'CONFIRMED', 'confirmed_by' => $this->caller()->auditUserId(),
            ]);
            foreach ($lines->values() as $i => $l) {
                $item = MaterialItem::query()->findOrFail($l->item_id);
                $this->receive($grn, $item, $for, $order?->id, (float) $wanted[$l->id]['qty'], $l->uom, $i + 1, $l->id, null, $rolls[$l->id]);
            }

            return $grn;
        });

        return ApiResponse::created($this->grnPayload($grn->fresh()));
    }

    /** Confirm Receive: material that came without a PR. */
    public function storeDirect(Request $request): JsonResponse
    {
        $this->allow('storematerial.receive');
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'buyer' => ['nullable', 'string', 'max:120'], 'po_number' => ['nullable', 'string', 'max:120'], 'style' => ['nullable', 'string', 'max:120'],
            'order_no' => ['nullable', 'string', 'max:60'], 'supplier' => ['nullable', 'string', 'max:255'], 'currency' => ['nullable', Rule::in(self::CURRENCIES)],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', Rule::exists('mp_items', 'id')->where('company_id', $company)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999'], 'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.rolls' => ['nullable', 'array', 'max:500'],
            'received_by' => ['nullable', 'string', 'max:40'], 'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'lines.required' => __('storematerial.grn.no_lines'), 'lines.*.item_id.required' => __('storematerial.grn.material_required'),
            'lines.*.qty.required' => __('storematerial.grn.qty_required'), 'lines.*.qty.gt' => __('storematerial.grn.qty_required'),
        ]);
        // Validation hands wildcard rows back in rule order; the form's own order is the line number.
        ksort($data['lines']);
        $receiver = StoreBook::employee($data['received_by'] ?? null, 'received_by');
        $for = array_map(fn ($v): string => trim((string) $v), ['buyer' => $data['buyer'] ?? '', 'po_number' => $data['po_number'] ?? '', 'style' => $data['style'] ?? '', 'order_no' => $data['order_no'] ?? '']);
        $items = MaterialItem::query()->whereIn('id', array_column($data['lines'], 'item_id'))->get()->keyBy('id');
        $errors = [];
        $seen = [];
        $rolls = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $item = $items[$l['item_id']];
            $rolls[$i] = $this->rolls($l['rolls'] ?? [], (float) $l['qty'], $item->kind, $item->uom, (string) $i, $errors, $seen);
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $grn = DB::transaction(function () use ($data, $for, $receiver, $rolls): Grn {
            $today = now()->toDateString();
            $supplier = trim((string) ($data['supplier'] ?? ''));
            $grn = Grn::query()->create($for + [
                'grn_no' => Procurement::nextNumber('GRN', 'sm_grns', 'grn_no', $today), 'grn_date' => $today, 'kind' => 'DIRECT',
                'supplier_id' => $supplier !== '' ? MaterialSupplier::query()->where('name', $supplier)->value('id') : null, 'supplier_name' => $supplier ?: null,
                'currency' => $data['currency'] ?? 'BDT', 'received_by' => $receiver?->id, 'remarks' => $data['remarks'] ?? null,
                'status' => 'CONFIRMED', 'confirmed_by' => $this->caller()->auditUserId(),
            ]);
            foreach (array_values($data['lines']) as $i => $l) {
                $item = MaterialItem::query()->findOrFail($l['item_id']);
                $this->receive($grn, $item, $for, null, (float) $l['qty'], $item->uom, $i + 1, null, isset($l['unit_price']) ? (float) $l['unit_price'] : null, $rolls[$i]);
            }

            return $grn;
        });

        return ApiResponse::created($this->grnPayload($grn->fresh()));
    }

    public function show(string $grn): JsonResponse
    {
        $this->allow('storematerial.view');

        return ApiResponse::ok($this->grnPayload($this->find($grn)));
    }

    /** Reverse: the GRN's quantities back out of stock and the PR's pending restored — refused once any of it is issued. */
    public function reverse(Request $request, string $grn): JsonResponse
    {
        $this->allow('storematerial.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => __('storematerial.grn.reason_required')]);
        $row = $this->find($grn);
        if ($row->status === 'REVERSED') {
            throw ValidationException::withMessages(['grn' => __('storematerial.grn.already_reversed', ['no' => $row->grn_no])]);
        }
        DB::transaction(function () use ($row, $data): void {
            $lines = GrnLine::query()->where('grn_id', $row->id)->get();
            Stock::query()->whereIn('id', $lines->pluck('stock_id'))->lockForUpdate()->get();
            $balances = StoreBook::balances($lines->pluck('stock_id')->unique()->values());
            $need = $lines->groupBy('stock_id')->map(fn ($set) => (float) $set->sum('qty'));
            foreach ($need as $stock => $qty) {
                if (($balances[$stock] ?? 0) + 0.0005 < $qty) {
                    throw ValidationException::withMessages(['grn' => __('storematerial.grn.issued', ['no' => $row->grn_no])]);
                }
            }
            // A roll that has gone out, even in part, holds its GRN too.
            $rolls = Roll::query()->where('grn_id', $row->id)->lockForUpdate()->get();
            $left = StoreBook::rollTotals($rolls->pluck('id'));
            foreach ($rolls as $r) {
                if (($left[$r->id]['available'] ?? 0) + 0.0005 < (float) $r->qty) {
                    throw ValidationException::withMessages(['grn' => __('storematerial.grn.issued', ['no' => $row->grn_no])]);
                }
            }
            $today = now()->toDateString();
            foreach ($lines as $l) {
                StoreBook::post(Stock::query()->findOrFail($l->stock_id), 'REVERSAL', $row->grn_no, $row->id, $today, out: (float) $l->qty, by: $this->caller()->auditUserId());
            }
            foreach ($rolls as $r) {
                StoreBook::moveRoll($r, 'REVERSAL', $row->grn_no, $row->id, $today, out: (float) $r->qty, by: $this->caller()->auditUserId());
            }
            $row->update(['status' => 'REVERSED', 'reversed_by' => $this->caller()->auditUserId(), 'reversed_at' => now(), 'reverse_reason' => $data['reason']]);
        });

        return ApiResponse::ok($this->grnPayload($row->fresh()));
    }

    /** Attachments: delivery challans, packing lists, photos. */
    public function attach(Request $request, string $grn, StoreFileAttachment $files): JsonResponse
    {
        $this->allow('storematerial.receive');
        $row = $this->find($grn);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'], 'name' => ['nullable', 'string', 'max:120']], [
            'file.required' => __('storematerial.grn.file_required'), 'file.max' => __('storematerial.grn.file_size'), 'file.mimes' => __('storematerial.grn.file_type'),
        ]);
        $files->handle($request->file('file'), 'sm_grn', $row->id, $this->caller()->auditUserId());
        if ($name = trim((string) $request->input('name'))) {
            FileAttachment::query()->where('attachable_type', 'sm_grn')->where('attachable_id', $row->id)->latest()->first()?->update(['original_name' => $name]);
        }

        return ApiResponse::created($this->grnPayload($row->fresh()));
    }

    /** @param list<array{roll_no: string, dye_lot: ?string, gsm: ?string, width: ?string, qty: float}> $rolls */
    private function receive(Grn $grn, MaterialItem $item, array $for, ?string $orderId, float $qty, string $uom, int $sort, ?string $lineId, ?float $price, array $rolls = []): void
    {
        $stock = StoreBook::bucket($item, $for, $orderId);
        $line = GrnLine::query()->create([
            'grn_id' => $grn->id, 'requisition_line_id' => $lineId, 'stock_id' => $stock->id, 'item_id' => $item->id, 'kind' => $item->kind,
            'uom' => $uom, 'qty' => round($qty, 3), 'unit_price' => $price, 'sort_order' => $sort,
        ]);
        $date = $grn->grn_date->toDateString();
        StoreBook::post($stock, 'GRN', $grn->grn_no, $grn->id, $date, in: round($qty, 3), by: $this->caller()->auditUserId());
        foreach ($rolls as $i => $r) {
            $roll = Roll::query()->create($r + ['grn_id' => $grn->id, 'grn_line_id' => $line->id, 'stock_id' => $stock->id, 'item_id' => $item->id, 'uom' => $uom, 'sort_order' => $i + 1]);
            StoreBook::moveRoll($roll, 'GRN', $grn->grn_no, $grn->id, $date, in: $r['qty'], by: $this->caller()->auditUserId());
        }
    }

    /**
     * A line's rolls, checked: fabric only; each with its number and quantity;
     * no number twice, nor one already in stock on a standing GRN; and all of
     * them adding up to what the line receives. A message goes under the
     * line's own key.
     *
     * @param  array<int, mixed>  $given
     * @param  array<string, string>  $errors
     * @param  array<string, true>  $seen  roll numbers already on this GRN
     * @return list<array{roll_no: string, dye_lot: ?string, gsm: ?string, width: ?string, qty: float}>
     */
    private function rolls(array $given, float $qty, string $kind, string $uom, string $key, array &$errors, array &$seen): array
    {
        $field = "lines.{$key}.rolls";
        $given = array_values(array_filter($given, 'is_array'));
        if ($given === []) {
            return [];
        }
        if ($kind !== 'FABRIC') {
            $errors[$field] = __('storematerial.roll.fabric_only');

            return [];
        }
        $out = [];
        foreach ($given as $n => $r) {
            $no = strtoupper(trim((string) ($r['roll_no'] ?? '')));
            $q = $r['qty'] ?? null;
            $text = fn (string $k, int $max): ?string => ($v = trim((string) ($r[$k] ?? ''))) === '' ? null : mb_substr($v, 0, $max);
            if ($no === '' || mb_strlen($no) > 40) {
                $errors[$field] = __('storematerial.roll.no_required', ['n' => $n + 1]);
            } elseif (! is_numeric($q) || (float) $q <= 0) {
                $errors[$field] = __('storematerial.roll.qty_required', ['roll' => $no]);
            } elseif (isset($seen[$no])) {
                $errors[$field] = __('storematerial.roll.duplicate', ['roll' => $no]);
            } elseif ($held = StoreBook::rollNamed($no)) {
                $grnNo = Grn::query()->whereKey($held->grn_id)->where('status', 'CONFIRMED')->value('grn_no');
                if ($grnNo) {
                    $errors[$field] = __('storematerial.roll.exists', ['roll' => $no, 'grn' => $grnNo]);
                }
            }
            if (isset($errors[$field])) {
                return [];
            }
            $seen[$no] = true;
            $out[] = ['roll_no' => $no, 'dye_lot' => $text('dye_lot', 60), 'gsm' => $text('gsm', 32), 'width' => $text('width', 32), 'qty' => round((float) $q, 3)];
        }
        $total = round(array_sum(array_column($out, 'qty')), 3);
        if (abs($total - round($qty, 3)) > 0.0005) {
            $errors[$field] = __('storematerial.roll.sum_mismatch', ['total' => StoreBook::qty($total), 'qty' => StoreBook::qty($qty), 'uom' => $uom]);

            return [];
        }

        return $out;
    }

    /** An approved PR, or Brickland's words for why not. */
    private function approvedPr(string $no): PurchaseRequisition
    {
        $no = strtoupper(trim($no));
        $pr = PurchaseRequisition::query()->where('pr_no', $no)->first() ?? throw ValidationException::withMessages(['pr_no' => __('storematerial.grn.pr_missing', ['pr' => $no])]);
        if ($pr->status !== 'APPROVED') {
            throw ValidationException::withMessages(['pr_no' => __('storematerial.grn.pr_not_approved', ['pr' => $no, 'status' => strtolower($pr->status)])]);
        }

        return $pr;
    }

    /** Whom an order's stock is held for. @return array{buyer: string, po_number: string, style: string, order_no: string} */
    private function orderFor(?Order $order): array
    {
        if (! $order) {
            return ['buyer' => '', 'po_number' => '', 'style' => '', 'order_no' => ''];
        }

        return [
            'buyer' => (string) Buyer::query()->whereKey($order->buyer_id)->value('short_name'), 'po_number' => (string) $order->po_no,
            'style' => (string) (Style::query()->whereKey($order->style_id)->value('style_ref') ?? $order->style_no), 'order_no' => (string) $order->order_no,
        ];
    }

    /** @return array<string, float> requisition line => received on confirmed GRNs */
    private function receivedOf(Collection $lineIds): array
    {
        return GrnLine::query()->join('sm_grns', 'sm_grns.id', '=', 'sm_grn_lines.grn_id')->where('sm_grns.status', 'CONFIRMED')
            ->whereIn('sm_grn_lines.requisition_line_id', $lineIds->all())->groupBy('sm_grn_lines.requisition_line_id')
            ->select('sm_grn_lines.requisition_line_id', DB::raw('sum(sm_grn_lines.qty) as q'))->pluck('q', 'requisition_line_id')->map(fn ($q): float => (float) $q)->all();
    }

    /** @return array<string, mixed> */
    private function prPayload(PurchaseRequisition $pr): array
    {
        $order = $pr->order_id ? Order::query()->find($pr->order_id) : null;
        $lines = PurchaseRequisitionLine::query()->where('requisition_id', $pr->id)->whereIn('status', PurchaseRequisitionLine::DELIVERY)->orderBy('sort_order')->get();
        $items = MaterialItem::query()->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');
        $received = $this->receivedOf($lines->pluck('id'));
        $names = Procurement::names([$pr->created_by]);

        return [
            'pr_no' => $pr->pr_no, 'status' => $pr->status, 'pr_date' => $pr->req_date->toDateString(),
            'merchandiser' => $names[$pr->created_by] ?? null,
            'department' => $pr->department_id ? DB::table('departments')->where('id', $pr->department_id)->value('name') : null,
            'supplier' => MaterialSupplier::query()->whereIn('id', $lines->pluck('approved_supplier_id')->filter()->unique())->pluck('name')->implode(', ') ?: null,
            'purchase_ref' => Quotation::query()->whereIn('id', $lines->pluck('approved_quotation_id')->filter())->pluck('qt_no')->implode(', ') ?: null,
            'required_date' => $lines->min(fn ($l) => $l->req_date?->toDateString()),
        ] + $this->orderFor($order) + [
            'items' => $lines->values()->map(function (PurchaseRequisitionLine $l, int $i) use ($items, $received): array {
                $item = $items[$l->item_id];
                $got = round($received[$l->id] ?? 0, 3);

                return [
                    'sl' => $i + 1, 'requisition_line_id' => $l->id, 'kind' => $l->kind, 'category' => $item->category, 'material' => StoreBook::material($item),
                    'uom' => $l->uom, 'pr_qty' => (float) $l->req_qty, 'received' => $got, 'pending' => round(max(0, (float) $l->req_qty - $got), 3),
                ];
            })->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function grnPayload(Grn $grn): array
    {
        $lines = GrnLine::query()->where('grn_id', $grn->id)->orderBy('sort_order')->get();
        $items = MaterialItem::query()->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');
        $prLines = PurchaseRequisitionLine::query()->whereIn('id', $lines->pluck('requisition_line_id')->filter())->get()->keyBy('id');
        // Received on confirmed GRNs before this one, and in all (this one included while it stands).
        $before = GrnLine::query()->join('sm_grns', 'sm_grns.id', '=', 'sm_grn_lines.grn_id')->where('sm_grns.status', 'CONFIRMED')
            ->whereIn('sm_grn_lines.requisition_line_id', $prLines->keys()->all())->where('sm_grns.grn_no', '<', $grn->grn_no)
            ->groupBy('sm_grn_lines.requisition_line_id')->select('sm_grn_lines.requisition_line_id', DB::raw('sum(sm_grn_lines.qty) as q'))->pluck('q', 'requisition_line_id');
        $people = Procurement::names([$grn->confirmed_by, $grn->reversed_by]);
        $rolls = Roll::query()->where('grn_id', $grn->id)->orderBy('sort_order')->get()->groupBy('grn_line_id');

        return [
            'grn_no' => $grn->grn_no, 'grn_date' => $grn->grn_date->toDateString(), 'kind' => $grn->kind, 'status' => $grn->status,
            'pr_no' => $grn->requisition_id ? PurchaseRequisition::query()->whereKey($grn->requisition_id)->value('pr_no') : null,
            'merchandiser' => $grn->requisition_id ? (Procurement::names([PurchaseRequisition::query()->whereKey($grn->requisition_id)->value('created_by')])[PurchaseRequisition::query()->whereKey($grn->requisition_id)->value('created_by')] ?? null) : null,
            'buyer' => $grn->buyer ?: null, 'po_number' => $grn->po_number ?: null, 'style' => $grn->style ?: null, 'order_no' => $grn->order_no ?: null,
            'supplier' => $grn->supplier_name ?? MaterialSupplier::query()->whereKey($grn->supplier_id)->value('name'),
            'warehouse' => $lines->pluck('kind')->unique()->map(fn ($k) => $k === 'FABRIC' ? 'FABRIC' : 'TRIMS')->values()->all(),
            'currency' => $grn->currency, 'received_by' => StoreBook::person($grn->received_by ? HrEmployee::query()->find($grn->received_by) : null),
            'confirmed_by' => $people[$grn->confirmed_by] ?? null, 'remarks' => $grn->remarks,
            'reversed_by' => $people[$grn->reversed_by] ?? null, 'reversed_at' => $grn->reversed_at?->toIso8601String(), 'reverse_reason' => $grn->reverse_reason,
            'lines' => $lines->map(function (GrnLine $l) use ($items, $prLines, $before, $grn, $rolls): array {
                $pr = $l->requisition_line_id ? $prLines[$l->requisition_line_id] ?? null : null;
                $prev = round((float) ($before[$l->requisition_line_id] ?? 0), 3);
                $total = round($prev + ($grn->status === 'CONFIRMED' ? (float) $l->qty : 0), 3);

                return [
                    'sl' => $l->sort_order, 'kind' => $l->kind, 'material' => StoreBook::material($items[$l->item_id]), 'uom' => $l->uom, 'qty' => (float) $l->qty,
                    'unit_price' => $l->unit_price === null ? null : (float) $l->unit_price,
                    'pr_qty' => $pr ? (float) $pr->req_qty : null, 'prev' => $pr ? $prev : null, 'total' => $pr ? $total : null,
                    'pending' => $pr ? round(max(0, (float) $pr->req_qty - $total), 3) : null, 'warehouse' => $l->kind,
                    'rolls' => ($rolls[$l->id] ?? collect())->map(fn (Roll $r): array => [
                        'roll_no' => $r->roll_no, 'dye_lot' => $r->dye_lot, 'gsm' => $r->gsm, 'width' => $r->width, 'qty' => (float) $r->qty,
                    ])->values()->all(),
                ];
            })->values()->all(),
            'attachments' => FileAttachment::query()->where('attachable_type', 'sm_grn')->where('attachable_id', $grn->id)->latest()->get()
                ->map(fn (FileAttachment $f): array => $f->toApiSummary())->values()->all(),
        ];
    }

    private function find(string $no): Grn
    {
        return Grn::query()->where('grn_no', $no)->firstOrFail();
    }
}
