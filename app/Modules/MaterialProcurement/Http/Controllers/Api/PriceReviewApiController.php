<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Http\Controllers\Api;

use App\Modules\Identity\Models\User;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\PurchaseRequisition;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\MaterialProcurement\Models\QuotationRevision;
use App\Modules\MaterialProcurement\Models\SupplierLogin;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Modules\Merchandise\Models\Buyer;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Merchandise\Models\Style;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Material Procurement → Price Review & Approval (AnnotechERP 08/05): every
 * supplier's latest price on each line of a submitted requisition, compared
 * line by line. A quotation is sent back for negotiation (the supplier
 * answers with a new revision), rejected with a reason, or approved and
 * handed to a follow-up merchandiser — the line's other quotations closed.
 * A line no supplier should have is rejected outright. Once every line of a
 * requisition is decided, the requisition is Approved (or Rejected, if none
 * was approved). Supplier prices are seen here only.
 */
class PriceReviewApiController extends ApiController
{
    /** The line statuses a review leaves — approved (and on to its delivery steps) or rejected; anything else is still open for quotation. */
    private const DECIDED = [...PurchaseRequisitionLine::DELIVERY, 'REJECTED'];

    public function index(): JsonResponse
    {
        $this->allow('material.price.approve');
        $lines = PurchaseRequisitionLine::query()
            ->join('mp_requisitions', 'mp_requisitions.id', '=', 'mp_requisition_lines.requisition_id')
            ->where('mp_requisitions.status', '!=', 'DRAFT')
            ->orderByDesc('mp_requisitions.pr_no')->orderBy('mp_requisition_lines.sort_order')
            ->get(['mp_requisition_lines.*']);
        $quotes = Quotation::query()->whereIn('requisition_line_id', $lines->pluck('id'))->orderBy('qt_no')->get();

        return ApiResponse::ok([
            'counts' => [
                'pending' => $quotes->whereIn('status', ['PENDING', 'REVISED'])->filter(fn ($q) => ! in_array($lines->firstWhere('id', $q->requisition_line_id)->status, self::DECIDED, true))->count(),
                'negotiation' => $quotes->where('status', 'NEGOTIATION')->count(),
                'approved' => $lines->whereIn('status', PurchaseRequisitionLine::DELIVERY)->count(),
                'rejected' => $lines->where('status', 'REJECTED')->count(),
            ],
            'lines' => $this->present($lines, $quotes),
            'merchandisers' => $this->merchandisers(),
        ]);
    }

    /** Request negotiation: the supplier is asked for a revised price, with a message. */
    public function negotiate(Request $request, string $quotation): JsonResponse
    {
        $this->allow('material.price.approve');
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']], ['message.required' => __('materialprocurement.review.message_required')]);
        $q = $this->open($quotation, ['PENDING', 'REVISED']);
        $q->update(['status' => 'NEGOTIATION', 'negotiation_message' => $data['message'], 'negotiated_at' => now()]);
        $this->tell($q, 'MP_QUOTATION_NEGOTIATION', ['message' => $data['message']]);
        Procurement::audit('UPDATE', 'price-review', $q->qt_no, Procurement::en('materialprocurement.audit.negotiated', ['qt' => $q->qt_no, 'supplier' => $this->supplierName($q), 'message' => $data['message']]));

        return ApiResponse::ok(['qt_no' => $q->qt_no, 'status' => $q->status, 'supplier' => $this->supplierName($q)]);
    }

    /** Reject one quotation: the supplier is told, with the reason. */
    public function reject(Request $request, string $quotation): JsonResponse
    {
        $this->allow('material.price.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => __('materialprocurement.review.reason_required')]);
        $q = $this->open($quotation, Quotation::REVISABLE);
        $q->update(['status' => 'REJECTED', 'reject_reason' => $data['reason'], 'decided_by' => $this->caller()->auditUserId(), 'decided_at' => now()]);
        $this->tell($q, 'MP_QUOTATION_REJECTED', ['reason' => $data['reason']]);
        $facts = $this->facts($q);
        Procurement::audit('UPDATE', 'price-review', $q->qt_no, Procurement::en('materialprocurement.audit.rejected', ['qt' => $q->qt_no, 'supplier' => $this->supplierName($q), 'item' => $facts['item'], 'pr' => $facts['pr'], 'reason' => $data['reason']]));

        return ApiResponse::ok(['qt_no' => $q->qt_no, 'status' => $q->status]);
    }

    /**
     * Approve & Handover: the quotation approved, the line's supplier chosen
     * and handed to the follow-up merchandiser; every other open quotation on
     * the line is closed.
     */
    public function approve(Request $request, string $quotation): JsonResponse
    {
        $this->allow('material.price.approve');
        $data = $request->validate(['handover_to' => ['required', 'string']], ['handover_to.required' => __('materialprocurement.review.handover_required')]);
        $merchandiser = collect($this->merchandisers())->firstWhere('value', $data['handover_to'])
            ?? throw ValidationException::withMessages(['handover_to' => __('materialprocurement.review.handover_required')]);
        $q = $this->open($quotation, Quotation::REVISABLE);
        $me = $this->caller()->auditUserId();
        DB::transaction(function () use ($q, $merchandiser, $me): void {
            $line = PurchaseRequisitionLine::query()->lockForUpdate()->findOrFail($q->requisition_line_id);
            $q->update(['status' => 'APPROVED', 'decided_by' => $me, 'decided_at' => now()]);
            Quotation::query()->where('requisition_line_id', $line->id)->where('id', '!=', $q->id)->whereIn('status', Quotation::REVISABLE)
                ->update(['status' => 'CLOSED', 'decided_by' => $me, 'decided_at' => now()]);
            $line->update(['status' => 'APPROVED', 'approved_supplier_id' => $q->supplier_id, 'approved_quotation_id' => $q->id, 'followup_user_id' => $merchandiser['value'], 'decided_by' => $me, 'decided_at' => now()]);
            $this->settle($line->requisition_id);
        });
        $latest = $this->latest($q);
        $this->tell($q, 'MP_QUOTATION_APPROVED', ['price' => "{$latest->currency} ".number_format((float) $latest->unit_price, 2)]);
        $facts = $this->facts($q);
        app(NotificationDispatcher::class)->sendToMany(User::query()->whereKey($merchandiser['value'])->get(), eventType: 'MP_MATERIAL_ASSIGNED',
            data: ['item' => $facts['item'], 'supplier' => $this->supplierName($q), 'pr' => $facts['pr'], 'qt' => $q->qt_no],
            entityType: 'mp_quotation', entityId: $q->id, actionUrl: '/material-procurement/assigned-materials');
        Procurement::audit('UPDATE', 'price-review', $q->qt_no, Procurement::en('materialprocurement.audit.approved', [
            'qt' => $q->qt_no, 'supplier' => $this->supplierName($q), 'item' => $facts['item'], 'pr' => $facts['pr'],
            'price' => $latest->currency.' '.Procurement::qty((float) $latest->unit_price), 'revision' => $latest->revision, 'to' => $merchandiser['label'],
        ]));

        return ApiResponse::ok(['qt_no' => $q->qt_no, 'status' => 'APPROVED', 'handover_to' => $merchandiser['label']]);
    }

    /** Reject line: no supplier is chosen and every open quotation on the line is rejected. */
    public function rejectLine(Request $request, string $line): JsonResponse
    {
        $this->allow('material.price.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => __('materialprocurement.review.reason_required')]);
        $me = $this->caller()->auditUserId();
        $row = PurchaseRequisitionLine::query()->findOrFail($line);
        if (in_array($row->status, self::DECIDED, true)) {
            throw ValidationException::withMessages(['line' => __('materialprocurement.review.line_decided')]);
        }
        $rejected = DB::transaction(function () use ($row, $data, $me): Collection {
            $open = Quotation::query()->where('requisition_line_id', $row->id)->whereIn('status', Quotation::REVISABLE)->get();
            Quotation::query()->whereIn('id', $open->pluck('id'))->update(['status' => 'REJECTED', 'reject_reason' => $data['reason'], 'decided_by' => $me, 'decided_at' => now()]);
            $row->update(['status' => 'REJECTED', 'reject_reason' => $data['reason'], 'decided_by' => $me, 'decided_at' => now()]);
            $this->settle($row->requisition_id);

            return $open;
        });
        foreach ($rejected as $q) {
            $this->tell($q, 'MP_QUOTATION_REJECTED', ['reason' => $data['reason']]);
        }
        $pr = PurchaseRequisition::query()->whereKey($row->requisition_id)->value('pr_no');
        Procurement::audit('UPDATE', 'price-review', $pr, Procurement::en('materialprocurement.audit.line_rejected', ['item' => MaterialItem::query()->whereKey($row->item_id)->value('name'), 'pr' => $pr, 'reason' => $data['reason']]));

        return ApiResponse::ok(['line_id' => $row->id, 'status' => 'REJECTED', 'quotations' => $rejected->count()]);
    }

    /** A quotation the review may still act on, on a line still open. */
    private function open(string $quotation, array $statuses): Quotation
    {
        $q = Quotation::query()->findOrFail($quotation);
        $line = PurchaseRequisitionLine::query()->findOrFail($q->requisition_line_id);
        if (in_array($line->status, self::DECIDED, true)) {
            throw ValidationException::withMessages(['line' => __('materialprocurement.review.line_decided')]);
        }
        if (! in_array($q->status, $statuses, true)) {
            throw ValidationException::withMessages(['quotation' => __('materialprocurement.review.wrong_status', ['status' => __('materialprocurement.sp.status.'.$q->status)])]);
        }

        return $q;
    }

    /** Every line decided: the requisition is Approved if any line was, Rejected if none. */
    private function settle(string $requisitionId): void
    {
        $statuses = PurchaseRequisitionLine::query()->where('requisition_id', $requisitionId)->pluck('status');
        if ($statuses->every(fn ($s) => in_array($s, self::DECIDED, true))) {
            PurchaseRequisition::query()->whereKey($requisitionId)->update(['status' => $statuses->contains(fn ($s) => $s !== 'REJECTED') ? 'APPROVED' : 'REJECTED']);
        }
    }

    /** The supplier's portal logins hear of a decision on their quotation. */
    private function tell(Quotation $q, string $event, array $extra): void
    {
        $users = User::query()->whereIn('id', SupplierLogin::query()->where('supplier_id', $q->supplier_id)->where('active', true)->pluck('user_id'))->get();
        $facts = $this->facts($q);
        app(NotificationDispatcher::class)->sendToMany($users, eventType: $event, data: ['qt' => $q->qt_no, 'item' => $facts['item'], 'pr' => $facts['pr']] + $extra,
            entityType: 'mp_quotation', entityId: $q->id, actionUrl: '/material-procurement/quotation-history');
    }

    /** @return array{item: ?string, pr: ?string} */
    private function facts(Quotation $q): array
    {
        $line = PurchaseRequisitionLine::query()->find($q->requisition_line_id);

        return ['item' => MaterialItem::query()->whereKey($line?->item_id)->value('name'), 'pr' => PurchaseRequisition::query()->whereKey($line?->requisition_id)->value('pr_no')];
    }

    private function supplierName(Quotation $q): ?string
    {
        return MaterialSupplier::query()->whereKey($q->supplier_id)->value('name');
    }

    private function latest(Quotation $q): QuotationRevision
    {
        return QuotationRevision::query()->where('quotation_id', $q->id)->orderByDesc('revision')->firstOrFail();
    }

    /**
     * Who an approved material may be handed to: the company's active people
     * whose roles open Assigned Materials (`material.followup.view`) — a
     * supplier's portal login never does.
     *
     * @return list<array{value: string, label: string}>
     */
    private function merchandisers(): array
    {
        return Procurement::usersWith('material.followup.view')
            ->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function present(Collection $lines, Collection $quotes): array
    {
        $prs = PurchaseRequisition::query()->whereIn('id', $lines->pluck('requisition_id'))->get()->keyBy('id');
        $orders = Order::query()->whereIn('id', $prs->pluck('order_id')->filter())->get()->keyBy('id');
        $buyers = Buyer::query()->whereIn('id', $orders->pluck('buyer_id')->filter())->pluck('short_name', 'id');
        $styles = Style::query()->whereIn('id', $orders->pluck('style_id')->filter())->pluck('style_ref', 'id');
        $items = MaterialItem::query()->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $quotes->pluck('supplier_id'))->get(['id', 'code', 'name'])->keyBy('id');
        $latest = QuotationRevision::query()->whereIn('quotation_id', $quotes->pluck('id'))->orderBy('revision')->get()->groupBy('quotation_id')->map->last();
        $names = Procurement::names([...$prs->pluck('created_by'), ...$lines->pluck('followup_user_id')]);
        $byLine = $quotes->groupBy('requisition_line_id');

        return $lines->map(function (PurchaseRequisitionLine $l) use ($prs, $orders, $buyers, $styles, $items, $suppliers, $latest, $names, $byLine): array {
            $pr = $prs[$l->requisition_id];
            $order = $pr->order_id ? ($orders[$pr->order_id] ?? null) : null;
            $item = $items[$l->item_id] ?? null;
            $open = ! in_array($l->status, self::DECIDED, true);

            return [
                'line_id' => $l->id, 'pr_no' => $pr->pr_no, 'req_type' => $pr->req_type, 'kind' => $l->kind, 'status' => $l->status, 'open' => $open,
                'item_name' => $item?->name, 'item_detail' => implode(' · ', array_filter([__('materialprocurement.pr.kind.'.$l->kind), $item?->colour, $l->specification])),
                'req_qty' => (float) $l->req_qty, 'uom' => $l->uom, 'req_date' => $l->req_date->toDateString(), 'requested_by' => $names[$pr->created_by] ?? null,
                'order' => $order ? ['order_no' => $order->order_no, 'buyer' => $buyers[$order->buyer_id] ?? null, 'style' => $styles[$order->style_id] ?? null] : null,
                'followup' => $l->followup_user_id ? ($names[$l->followup_user_id] ?? null) : null, 'reject_reason' => $l->reject_reason,
                'quotations' => ($byLine[$l->id] ?? collect())->map(function (Quotation $q) use ($suppliers, $latest, $open): array {
                    $r = $latest[$q->id] ?? null;

                    return [
                        'id' => $q->id, 'qt_no' => $q->qt_no, 'supplier' => $suppliers[$q->supplier_id]->name ?? null, 'supplier_code' => $suppliers[$q->supplier_id]->code ?? null,
                        'unit_price' => $r ? (float) $r->unit_price : null, 'currency' => $r?->currency, 'quoted_qty' => $r ? (float) $r->quoted_qty : null,
                        'lead_time_days' => $r?->lead_time_days, 'expected_delivery' => $r?->expected_delivery?->toDateString(), 'revisions' => $q->revisions, 'status' => $q->status,
                        'negotiation_message' => $q->negotiation_message, 'reject_reason' => $q->reject_reason,
                        'can_approve' => $open && in_array($q->status, Quotation::REVISABLE, true), 'can_negotiate' => $open && in_array($q->status, ['PENDING', 'REVISED'], true),
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }
}
