<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Store;
use App\Modules\MaterialProcurement\Models\Followup;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\PurchaseRequisition;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\MaterialProcurement\Models\QuotationRevision;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Modules\Merchandise\Http\Controllers\Api\AccReceiptApiController;
use App\Modules\Merchandise\Http\Controllers\Api\PurchasedFabricMrrApiController;
use App\Modules\Merchandise\Models\AccPurchaseOrder;
use App\Modules\Merchandise\Models\AccReceipt;
use App\Modules\Merchandise\Models\AccReceiptLine;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\PurchasedFabricMrr;
use App\Modules\Merchandise\Models\PurchasedFabricMrrLine;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Tenancy\Models\Company;
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
 * Material Procurement → Approved Material & Follow-Up (AnnotechERP 08/06):
 * Assigned Materials — the approved lines handed to the caller (or, to a
 * monitor, all of them, read-only), each opening to its order, requisition,
 * approval, supplier and material details and its follow-ups; the
 * merchandiser logs a follow-up (the delivery step reached, what was
 * received in this delivery) and raises a receipt as a draft goods receipt
 * on Merchandise Desk. Delivery Tracking: every approved line's step,
 * received and balance quantity and next follow-up.
 */
class AssignedMaterialApiController extends ApiController
{
    /** The line statuses past which nothing more is received or followed up. */
    private const CLOSED = ['IN_HOUSE'];

    /** Assigned Materials: the caller's own lines, or every approved line (`?scope=all`, the monitor's view). */
    public function index(Request $request): JsonResponse
    {
        $this->allow('material.followup.view');
        $all = $request->query('scope') === 'all';
        if ($all) {
            $this->allow('material.followup.monitor');
        }
        $me = $this->caller()->auditUserId();
        $lines = $this->approvedLines()->when(! $all, fn (Collection $c) => $c->where('followup_user_id', $me)->values());

        return ApiResponse::ok(['rows' => $this->rows($lines), 'can_monitor' => $this->caller()->can('material.followup.monitor'), 'statuses' => PurchaseRequisitionLine::DELIVERY]);
    }

    /** Delivery Tracking: every approved line, its step, quantities, merchandisers and next follow-up. */
    public function tracking(): JsonResponse
    {
        $this->allow('material.followup.monitor');

        return ApiResponse::ok(['rows' => $this->rows($this->approvedLines())]);
    }

    /** One approved line in full: its facts, the delivery steps and its follow-ups. */
    public function show(string $line): JsonResponse
    {
        $this->allow('material.followup.view');
        $row = $this->line($line);
        $this->assertCanSee($row);

        return ApiResponse::ok($this->detail($row));
    }

    /** Add follow-up: the step reached, what was received in this delivery; the line moves to that step. */
    public function followup(Request $request, string $line): JsonResponse
    {
        $this->allow('material.followup.view');
        $row = $this->line($line);
        $this->assertMine($row);
        if (in_array($row->status, self::CLOSED, true)) {
            throw ValidationException::withMessages(['line' => __('materialprocurement.fu.closed')]);
        }
        $data = $request->validate([
            'followup_date' => ['required', 'date'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(PurchaseRequisitionLine::DELIVERY)],
            'received_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'expected_delivery' => ['nullable', 'date'],
            'actual_delivery' => ['nullable', 'date'],
            'supplier_response' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'next_followup_date' => ['nullable', 'date'],
        ], ['followup_date.required' => __('materialprocurement.fu.date_required'), 'status.required' => __('materialprocurement.fu.status_required'), 'status.in' => __('materialprocurement.fu.status_required')]);
        $received = round((float) ($data['received_qty'] ?? 0), 3);
        DB::transaction(function () use ($row, $data, $received): void {
            $line = PurchaseRequisitionLine::query()->lockForUpdate()->findOrFail($row->id);
            $balance = round((float) $line->req_qty - $this->receivedOf($line->id), 3);
            if ($received > $balance + 0.0005) {
                throw ValidationException::withMessages(['received_qty' => __('materialprocurement.fu.over', ['balance' => rtrim(rtrim(number_format($balance, 3, '.', ''), '0'), '.'), 'uom' => $line->uom])]);
            }
            Followup::query()->create([
                'requisition_line_id' => $line->id, 'followup_date' => $data['followup_date'], 'contact_person' => $data['contact_person'] ?? null, 'status' => $data['status'],
                'received_qty' => $received, 'expected_delivery' => $data['expected_delivery'] ?? null, 'actual_delivery' => $data['actual_delivery'] ?? null,
                'supplier_response' => $data['supplier_response'] ?? null, 'remarks' => $data['remarks'] ?? null, 'next_followup_date' => $data['next_followup_date'] ?? null,
                'created_by' => $this->caller()->auditUserId(),
            ]);
            $line->update(['status' => $data['status']]);
        });
        $fresh = $row->fresh();
        $pr = PurchaseRequisition::query()->whereKey($fresh->requisition_id)->value('pr_no');
        Procurement::audit('CREATE', 'assigned-materials', $pr, Procurement::en('materialprocurement.audit.followup', [
            'item' => MaterialItem::query()->whereKey($fresh->item_id)->value('name'), 'pr' => $pr, 'status' => $data['status'], 'received' => Procurement::qty($received),
            'total' => Procurement::qty($this->receivedOf($fresh->id)), 'required' => Procurement::qty((float) $fresh->req_qty), 'uom' => $fresh->uom,
        ]));
        if ($received > 0) {
            $this->announceReceipt($fresh);
        }

        return ApiResponse::created($this->detail($row->fresh()));
    }

    /**
     * Raise GRN: a delivery's received quantity as a draft goods receipt on
     * Merchandise Desk — a purchased-fabric receipt (MRR-IMF) for a fabric
     * line, an accessories receipt (MRR-ACC) for an accessory — verified and
     * finalised onto stock on that receiving screen.
     */
    public function raiseGrn(Request $request, string $followup): JsonResponse
    {
        $this->allow('material.followup.view');
        $fu = Followup::query()->findOrFail($followup);
        $row = $this->line($fu->requisition_line_id);
        $this->assertMine($row);
        $company = app(TenantContext::class)->companyId();
        $data = $request->validate([
            'unit_company_id' => ['required', Rule::in([$company])],
            'store_id' => ['required', Rule::exists('stores', 'id')->where('company_id', $company)],
            'challan_no' => ['nullable', 'string', 'max:60'],
        ], ['unit_company_id.required' => __('materialprocurement.fu.company_required'), 'unit_company_id.in' => __('materialprocurement.fu.company_required'), 'store_id.required' => __('materialprocurement.fu.store_required')]);
        if ((float) $fu->received_qty <= 0) {
            throw ValidationException::withMessages(['followup' => __('materialprocurement.fu.nothing_received')]);
        }
        if ($fu->grn_no) {
            throw ValidationException::withMessages(['followup' => __('materialprocurement.fu.grn_raised', ['no' => $fu->grn_no])]);
        }
        $quote = Quotation::query()->findOrFail($row->approved_quotation_id);
        $price = QuotationRevision::query()->where('quotation_id', $quote->id)->orderByDesc('revision')->firstOrFail();
        if (! in_array($price->currency, AccPurchaseOrder::CURRENCIES, true)) {
            throw ValidationException::withMessages(['followup' => __('materialprocurement.fu.currency', ['currency' => $price->currency])]);
        }
        $pr = PurchaseRequisition::query()->findOrFail($row->requisition_id);
        $item = MaterialItem::query()->findOrFail($row->item_id);
        $today = now()->toDateString();
        $qty = round((float) $fu->received_qty, 4);
        $amount = round($qty * (float) $price->unit_price, 2);
        $header = [
            'unit_company_id' => $company, 'supplier_id' => $quote->supplier_id, 'store_id' => $data['store_id'], 'mrr_date' => $today, 'payment_method' => 'LC', 'source_type' => 'LOCAL',
            'challan_no' => trim((string) ($data['challan_no'] ?? '')), 'challan_date' => $today, 'currency' => $price->currency, 'conv_rate' => 1,
            'remarks' => __('materialprocurement.fu.grn_remarks', ['pr' => $pr->pr_no, 'qt' => $quote->qt_no]), 'status' => 'DRAFT', 'created_by' => $this->caller()->auditUserId(),
            'goods_value' => $amount, 'other_cost' => 0, 'landed_cost' => $amount, 'vat_base' => $amount, 'vat' => 0, 'tds' => 0, 'vds' => 0, 'net_payable' => $amount,
        ];
        $grn = DB::transaction(function () use ($fu, $row, $item, $header, $qty, $amount, $price, $today): array {
            if ($row->kind === 'FABRIC') {
                $mrr = PurchasedFabricMrr::query()->create($header + ['mrr_no' => PurchasedFabricMrrApiController::nextNumber($today), 'net_amount' => $amount]);
                PurchasedFabricMrrLine::query()->create([
                    'mrr_id' => $mrr->id, 'po_line_id' => null, 'mp_requisition_line_id' => $row->id, 'description' => $item->name, 'gsm' => $item->gsm, 'uom' => $row->uom,
                    'receive_qty' => $qty, 'reject_qty' => 0, 'accept_qty' => $qty, 'rate' => (float) $price->unit_price, 'amount' => $amount, 'landed_amount' => $amount, 'sort_order' => 1,
                ]);
                $out = ['type' => 'FABRIC', 'id' => $mrr->id, 'no' => $mrr->mrr_no];
            } else {
                $mrr = AccReceipt::query()->create($header + ['mrr_no' => AccReceiptApiController::nextNumber($today), 'mrr_type' => 'PO']);
                AccReceiptLine::query()->create([
                    'mrr_id' => $mrr->id, 'item_id' => $item->id, 'po_line_id' => null, 'mp_requisition_line_id' => $row->id, 'description' => $item->name, 'uom' => $row->uom,
                    'qty' => $qty, 'rate' => (float) $price->unit_price, 'amount' => $amount, 'landed_amount' => $amount, 'sort_order' => 1,
                ]);
                $out = ['type' => 'TRIMS', 'id' => $mrr->id, 'no' => $mrr->mrr_no];
            }
            $fu->update(['grn_type' => $out['type'], 'grn_id' => $out['id'], 'grn_no' => $out['no']]);

            return $out;
        });

        Procurement::audit('CREATE', 'assigned-materials', $grn['no'], Procurement::en('materialprocurement.audit.grn', ['grn' => $grn['no'], 'item' => $item->name, 'pr' => $pr->pr_no, 'qty' => Procurement::qty($qty)]));

        return ApiResponse::created(['grn_no' => $grn['no'], 'grn_type' => $grn['type'], 'line' => $this->detail($row->fresh())]);
    }

    /** What Raise GRN picks from: the company and its stores. */
    public function grnOptions(): JsonResponse
    {
        $this->allow('material.followup.view');
        $company = app(TenantContext::class)->companyId();

        return ApiResponse::ok([
            'companies' => [['value' => $company, 'label' => Company::query()->whereKey($company)->value('name')]],
            'stores' => Store::query()->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($s): array => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})"])->values()->all(),
        ]);
    }

    /** Material Received: whoever raised the requisition and whoever approved the price hear what has come in so far. */
    private function announceReceipt(PurchaseRequisitionLine $line): void
    {
        $pr = PurchaseRequisition::query()->findOrFail($line->requisition_id);
        $approver = Quotation::query()->whereKey($line->approved_quotation_id)->value('decided_by');
        $ids = array_diff(array_unique(array_filter([$pr->created_by, $approver])), [$this->caller()->auditUserId()]);
        app(NotificationDispatcher::class)->sendToMany(User::query()->whereKey($ids)->get(), eventType: 'MP_MATERIAL_RECEIVED',
            data: ['item' => MaterialItem::query()->whereKey($line->item_id)->value('name'), 'pr' => $pr->pr_no, 'received' => Procurement::qty($this->receivedOf($line->id)), 'required' => Procurement::qty((float) $line->req_qty), 'uom' => $line->uom],
            entityType: 'mp_requisition_line', entityId: $line->id, actionUrl: '/material-procurement/my-requisitions');
    }

    private function line(string $id): PurchaseRequisitionLine
    {
        return PurchaseRequisitionLine::query()->whereIn('status', PurchaseRequisitionLine::DELIVERY)->findOrFail($id);
    }

    /** The line handed to the caller, or a monitor's. */
    private function assertCanSee(PurchaseRequisitionLine $row): void
    {
        if ($row->followup_user_id !== $this->caller()->auditUserId() && ! $this->caller()->can('material.followup.monitor')) {
            throw ValidationException::withMessages(['line' => __('materialprocurement.fu.not_yours')]);
        }
    }

    /** Only the merchandiser it was handed to logs follow-ups and raises receipts; a monitor only looks. */
    private function assertMine(PurchaseRequisitionLine $row): void
    {
        if ($row->followup_user_id !== $this->caller()->auditUserId()) {
            throw ValidationException::withMessages(['line' => __('materialprocurement.fu.not_yours')]);
        }
    }

    private function receivedOf(string $lineId): float
    {
        return (float) Followup::query()->where('requisition_line_id', $lineId)->sum('received_qty');
    }

    /** Every approved line, newest requisition first. */
    private function approvedLines(): Collection
    {
        return PurchaseRequisitionLine::query()->whereIn('mp_requisition_lines.status', PurchaseRequisitionLine::DELIVERY)
            ->join('mp_requisitions', 'mp_requisitions.id', '=', 'mp_requisition_lines.requisition_id')
            ->orderByDesc('mp_requisitions.pr_no')->orderBy('mp_requisition_lines.sort_order')
            ->get(['mp_requisition_lines.*']);
    }

    /** @return list<array<string, mixed>> */
    private function rows(Collection $lines): array
    {
        $prs = PurchaseRequisition::query()->whereIn('id', $lines->pluck('requisition_id'))->get()->keyBy('id');
        $orders = Order::query()->whereIn('id', $prs->pluck('order_id')->filter())->pluck('order_no', 'id');
        $items = MaterialItem::query()->whereIn('id', $lines->pluck('item_id'))->pluck('name', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $lines->pluck('approved_supplier_id'))->pluck('name', 'id');
        $prices = QuotationRevision::query()->whereIn('quotation_id', $lines->pluck('approved_quotation_id'))->orderBy('revision')->get()->groupBy('quotation_id')->map->last();
        $fus = Followup::query()->whereIn('requisition_line_id', $lines->pluck('id'))->orderBy('followup_date')->orderBy('created_at')->get()->groupBy('requisition_line_id');
        $names = Procurement::names([...$prs->pluck('created_by'), ...$lines->pluck('followup_user_id')]);
        $today = now()->toDateString();

        return $lines->map(function (PurchaseRequisitionLine $l) use ($prs, $orders, $items, $suppliers, $prices, $fus, $names, $today): array {
            $pr = $prs[$l->requisition_id];
            $price = $prices[$l->approved_quotation_id] ?? null;
            $mine = $fus[$l->id] ?? collect();
            $received = round((float) $mine->sum('received_qty'), 3);
            $last = $mine->last();
            $next = $last?->next_followup_date?->toDateString();

            return [
                'line_id' => $l->id, 'item_name' => $items[$l->item_id] ?? null, 'pr_no' => $pr->pr_no, 'req_type' => $pr->req_type, 'order_no' => $pr->order_id ? ($orders[$pr->order_id] ?? null) : null,
                'supplier' => $suppliers[$l->approved_supplier_id] ?? null, 'price' => $price ? ['currency' => $price->currency, 'unit_price' => (float) $price->unit_price] : null,
                'req_qty' => (float) $l->req_qty, 'uom' => $l->uom, 'received' => $received, 'balance' => round(max(0, (float) $l->req_qty - $received), 3),
                'expected' => ($mine->whereNotNull('expected_delivery')->last()?->expected_delivery ?? $price?->expected_delivery)?->toDateString(),
                'status' => $l->status, 'requested_by' => $names[$pr->created_by] ?? null, 'followup' => $names[$l->followup_user_id] ?? null,
                'next_followup' => $next, 'next_overdue' => $next !== null && $next < $today && ! in_array($l->status, self::CLOSED, true),
                'mine' => $l->followup_user_id === $this->caller()->auditUserId(),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function detail(PurchaseRequisitionLine $l): array
    {
        $row = $this->rows(collect([$l]))[0];
        $pr = PurchaseRequisition::query()->findOrFail($l->requisition_id);
        $order = $pr->order_id ? Order::query()->find($pr->order_id) : null;
        $quote = Quotation::query()->find($l->approved_quotation_id);
        $price = $quote ? QuotationRevision::query()->where('quotation_id', $quote->id)->orderByDesc('revision')->first() : null;
        $supplier = MaterialSupplier::query()->find($l->approved_supplier_id);
        $item = MaterialItem::query()->find($l->item_id);
        $people = Procurement::names([$pr->created_by, $order?->created_by, $quote?->decided_by, $l->followup_user_id, ...Followup::query()->where('requisition_line_id', $l->id)->pluck('created_by')]);
        $fus = Followup::query()->where('requisition_line_id', $l->id)->orderBy('followup_date')->orderBy('created_at')->get();

        return $row + [
            'closed' => in_array($l->status, self::CLOSED, true), 'balance_line' => $row['balance'],
            'order' => $order ? [
                'order_no' => $order->order_no, 'buyer' => Buyer::query()->whereKey($order->buyer_id)->value('short_name'), 'style' => Style::query()->whereKey($order->style_id)->value('style_ref'),
                'order_qty' => (int) $order->total_qty, 'order_unit' => $order->unit, 'delivery_date' => $order->ship_date?->toDateString(),
            ] : null,
            'requisition' => [
                'pr_no' => $pr->pr_no, 'req_type' => $pr->req_type, 'req_date' => $pr->req_date->toDateString(), 'created_by' => $people[$pr->created_by] ?? null,
                'merchandiser' => $order ? ($people[$order->created_by] ?? null) : ($people[$pr->created_by] ?? null),
                'department' => $pr->department_id ? DB::table('departments')->where('id', $pr->department_id)->value('name') : null,
            ],
            'approval' => [
                'qt_no' => $quote?->qt_no, 'supplier' => $supplier?->name, 'price' => $price ? (float) $price->unit_price : null, 'currency' => $price?->currency,
                'lead_time_days' => $price?->lead_time_days, 'approved_by' => $quote?->decided_by ? ($people[$quote->decided_by] ?? null) : null,
                'approved_at' => $l->decided_at?->toIso8601String(), 'merchandiser' => $people[$l->followup_user_id] ?? null,
            ],
            'supplier_info' => [
                'code' => $supplier?->code, 'name' => $supplier?->name, 'contact_person' => $supplier?->owner_name, 'address' => $supplier?->address,
                'goods_service' => $supplier?->goods_service, 'vat_reg_no' => $supplier?->vat_reg_no, 'tin_no' => $supplier?->tin_no,
            ],
            'material' => [
                'item_code' => $item?->item_code, 'item' => $item?->name, 'category' => $item?->category, 'colour' => $item?->colour, 'size' => $item?->size,
                'gsm_width' => trim(implode(' / ', array_filter([$item?->gsm, $item?->width]))) ?: null, 'specification' => $l->specification, 'req_date' => $l->req_date->toDateString(),
                'expected_delivery' => $price?->expected_delivery?->toDateString(),
            ],
            'followups' => $fus->map(fn (Followup $f): array => [
                'id' => $f->id, 'date' => $f->followup_date->toDateString(), 'contact_person' => $f->contact_person, 'supplier_response' => $f->supplier_response, 'status' => $f->status,
                'expected_delivery' => $f->expected_delivery?->toDateString(), 'actual_delivery' => $f->actual_delivery?->toDateString(), 'received_qty' => (float) $f->received_qty,
                'next_followup_date' => $f->next_followup_date?->toDateString(), 'remarks' => $f->remarks, 'grn_no' => $f->grn_no, 'grn_type' => $f->grn_type,
                'by' => $people[$f->created_by] ?? null, 'can_raise_grn' => (float) $f->received_qty > 0 && ! $f->grn_no,
            ])->values()->all(),
        ];
    }
}
