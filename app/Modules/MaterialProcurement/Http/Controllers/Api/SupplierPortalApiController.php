<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Http\Controllers\Api;

use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\PurchaseRequisition;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\MaterialProcurement\Models\QuotationRevision;
use App\Modules\MaterialProcurement\Models\SupplierLogin;
use App\Modules\MaterialProcurement\Support\Procurement;
use App\Modules\Merchandise\Models\Order;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Material Procurement → Supplier Portal (AnnotechERP 08/04), seen by a
 * supplier's portal login: the Supplier Dashboard, Available Requirements —
 * every open line of a submitted requisition, quoted with a price, lead time
 * and delivery date — and Quotation History, its own quotations with every
 * price revision. A price is never edited: a new one is the next revision.
 * Anyone without a portal login is told the screens are the suppliers'.
 */
class SupplierPortalApiController extends ApiController
{
    /** Counts, the newest open requirements and the supplier's notifications. */
    public function dashboard(): JsonResponse
    {
        $this->allow('material.portal.view');
        $login = $this->login();
        if (! $login) {
            return ApiResponse::ok(['supplier' => null]);
        }
        $lines = $this->openLines();
        $mine = Quotation::query()->where('supplier_id', $login->supplier_id)->get();

        return ApiResponse::ok([
            'supplier' => MaterialSupplier::query()->whereKey($login->supplier_id)->value('name'), 'contact' => $login->contact_name,
            'counts' => [
                'pending' => $lines->count(), 'submitted' => $mine->count(),
                'approved' => $mine->where('status', 'APPROVED')->count(), 'ended' => $mine->whereIn('status', Quotation::ENDED)->count(),
            ],
            'recent' => array_slice($this->presentLines($lines, $login), 0, 5),
            'notifications' => Notification::query()->where('user_id', $login->user_id)->where('event_type', 'MP_REQUIREMENT_AVAILABLE')->latest()->limit(5)->get()
                ->map(fn (Notification $n): array => ['title' => $n->title, 'body' => $n->body, 'at' => $n->created_at->toIso8601String()])->values()->all(),
        ]);
    }

    /** Every line open for quotation, newest requisition first, with this supplier's quotation on it. */
    public function requirements(): JsonResponse
    {
        $this->allow('material.portal.view');
        $login = $this->login();

        return ApiResponse::ok(['supplier' => $login ? MaterialSupplier::query()->whereKey($login->supplier_id)->value('name') : null, 'currencies' => Quotation::CURRENCIES,
            'lines' => $login ? $this->presentLines($this->openLines(), $login) : []]);
    }

    /** This supplier's quotations, newest first, each with every revision it sent. */
    public function quotations(): JsonResponse
    {
        $this->allow('material.portal.view');
        $login = $this->login();
        if (! $login) {
            return ApiResponse::ok(['supplier' => null, 'quotations' => []]);
        }
        $rows = Quotation::query()->where('supplier_id', $login->supplier_id)->orderByDesc('qt_no')->get();
        $revisions = QuotationRevision::query()->whereIn('quotation_id', $rows->pluck('id'))->orderBy('revision')->get()->groupBy('quotation_id');
        $lines = PurchaseRequisitionLine::query()->whereIn('id', $rows->pluck('requisition_line_id'))->get()->keyBy('id');
        $prs = PurchaseRequisition::query()->whereIn('id', $lines->pluck('requisition_id'))->pluck('pr_no', 'id');
        $items = MaterialItem::query()->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');

        return ApiResponse::ok(['supplier' => MaterialSupplier::query()->whereKey($login->supplier_id)->value('name'), 'currencies' => Quotation::CURRENCIES,
            'quotations' => $rows->map(function (Quotation $q) use ($revisions, $lines, $prs, $items): array {
                $line = $lines[$q->requisition_line_id];
                $item = $items[$line->item_id] ?? null;
                $revs = $revisions[$q->id] ?? collect();

                return [
                    'id' => $q->id, 'qt_no' => $q->qt_no, 'status' => $q->status, 'revisable' => in_array($q->status, Quotation::REVISABLE, true),
                    'negotiation_message' => $q->negotiation_message, 'reject_reason' => $q->reject_reason,
                    'line' => $this->lineFacts($line, $prs[$line->requisition_id] ?? null, $item), 'latest' => $revs->last() ? $this->presentRevision($revs->last()) : null,
                    'revisions' => $revs->map(fn ($r) => $this->presentRevision($r))->values()->all(),
                ];
            })->values()->all()]);
    }

    /**
     * Submit quotation: the first price on a line, or — once quoted — its next
     * revision. Only an open line of a submitted requisition is quoted, and
     * only a quotation still under review is revised.
     */
    public function quote(Request $request, string $line): JsonResponse
    {
        $this->allow('material.portal.view');
        $login = $this->login() ?? throw ValidationException::withMessages(['supplier' => __('materialprocurement.sp.no_login')]);
        $row = PurchaseRequisitionLine::query()->findOrFail($line);
        $data = $request->validate([
            'quoted_qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['required', 'numeric', 'gt:0'],
            'currency' => ['required', Rule::in(Quotation::CURRENCIES)],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'expected_delivery' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'quoted_qty.*' => __('materialprocurement.sp.qty_required'), 'unit_price.*' => __('materialprocurement.sp.price_required'),
            'currency.*' => __('materialprocurement.sp.currency_required'), 'lead_time_days.*' => __('materialprocurement.sp.lead_required'),
            'expected_delivery.*' => __('materialprocurement.sp.delivery_required'),
        ]);
        $open = $row->status === 'OPEN_FOR_QUOTATION' && PurchaseRequisition::query()->whereKey($row->requisition_id)->where('status', 'SUBMITTED')->exists();
        if (! $open) {
            throw ValidationException::withMessages(['line' => __('materialprocurement.sp.line_closed')]);
        }
        $quotation = DB::transaction(function () use ($row, $login, $data): Quotation {
            $quotation = Quotation::query()->where('requisition_line_id', $row->id)->where('supplier_id', $login->supplier_id)->lockForUpdate()->first();
            if ($quotation && ! in_array($quotation->status, Quotation::REVISABLE, true)) {
                throw ValidationException::withMessages(['line' => __('materialprocurement.sp.not_revisable', ['status' => __('materialprocurement.sp.status.'.$quotation->status)])]);
            }
            $quotation ??= Quotation::query()->create([
                'qt_no' => Procurement::nextNumber('QT', 'mp_quotations', 'qt_no', now()->toDateString()), 'requisition_line_id' => $row->id, 'supplier_id' => $login->supplier_id, 'status' => 'PENDING', 'revisions' => 0,
            ]);
            $next = $quotation->revisions + 1;
            QuotationRevision::query()->create([
                'quotation_id' => $quotation->id, 'revision' => $next, 'quoted_qty' => $data['quoted_qty'], 'unit_price' => $data['unit_price'], 'currency' => $data['currency'],
                'lead_time_days' => $data['lead_time_days'], 'expected_delivery' => $data['expected_delivery'], 'remarks' => $data['remarks'] ?? null,
                'submitted_by' => $this->caller()->auditUserId(), 'submitted_at' => now(),
            ]);
            $quotation->update(['revisions' => $next, 'status' => $next === 1 ? 'PENDING' : 'REVISED']);

            return $quotation;
        });

        $this->record($quotation, $row, $data);

        return ApiResponse::created(['qt_no' => $quotation->qt_no, 'revision' => $quotation->revisions, 'status' => $quotation->status]);
    }

    /**
     * A quotation or revision sent: an audit trail entry, and the people who
     * review prices hear of it — New Supplier Quotation Received, or Supplier
     * Submitted a Revised Quotation.
     */
    private function record(Quotation $quotation, PurchaseRequisitionLine $line, array $data): void
    {
        $supplier = MaterialSupplier::query()->whereKey($quotation->supplier_id)->value('name');
        $item = MaterialItem::query()->whereKey($line->item_id)->value('name');
        $pr = PurchaseRequisition::query()->whereKey($line->requisition_id)->value('pr_no');
        Procurement::audit('CREATE', 'available-requirements', $quotation->qt_no, Procurement::en('materialprocurement.audit.quoted', [
            'supplier' => $supplier, 'qt' => $quotation->qt_no, 'revision' => $quotation->revisions, 'item' => $item, 'pr' => $pr,
            'price' => $data['currency'].' '.number_format((float) $data['unit_price'], 2), 'qty' => Procurement::qty((float) $data['quoted_qty']), 'days' => $data['lead_time_days'],
        ]));
        $first = $quotation->revisions === 1;
        app(NotificationDispatcher::class)->sendToMany(Procurement::usersWith('material.price.approve'), eventType: $first ? 'MP_QUOTATION_RECEIVED' : 'MP_QUOTATION_REVISED',
            data: ['supplier' => $supplier, 'item' => $item, 'pr' => $pr, 'qt' => $quotation->qt_no, 'revision' => $quotation->revisions],
            entityType: 'mp_quotation', entityId: $quotation->id, actionUrl: '/material-procurement/price-review');
    }

    /** The caller's active portal login, if it has one. */
    private function login(): ?SupplierLogin
    {
        $me = $this->caller()->auditUserId();

        return $me ? SupplierLogin::query()->where('user_id', $me)->where('active', true)->first() : null;
    }

    /** Lines open for quotation on submitted requisitions, newest first. */
    private function openLines(): Collection
    {
        return PurchaseRequisitionLine::query()->where('mp_requisition_lines.status', 'OPEN_FOR_QUOTATION')
            ->where('mp_requisitions.status', 'SUBMITTED')
            ->join('mp_requisitions', 'mp_requisitions.id', '=', 'mp_requisition_lines.requisition_id')
            ->orderByDesc('mp_requisitions.pr_no')->orderBy('mp_requisition_lines.sort_order')
            ->get(['mp_requisition_lines.*']);
    }

    /** @return list<array<string, mixed>> */
    private function presentLines(Collection $lines, SupplierLogin $login): array
    {
        $prs = PurchaseRequisition::query()->whereIn('id', $lines->pluck('requisition_id'))->get()->keyBy('id');
        $orders = Order::query()->whereIn('id', $prs->pluck('order_id')->filter())->pluck('order_no', 'id');
        $items = MaterialItem::query()->whereIn('id', $lines->pluck('item_id'))->get()->keyBy('id');
        $quotes = Quotation::query()->where('supplier_id', $login->supplier_id)->whereIn('requisition_line_id', $lines->pluck('id'))->get()->keyBy('requisition_line_id');
        $latest = QuotationRevision::query()->whereIn('quotation_id', $quotes->pluck('id'))->orderBy('revision')->get()->groupBy('quotation_id')->map->last();

        return $lines->map(function (PurchaseRequisitionLine $l) use ($prs, $orders, $items, $quotes, $latest): array {
            $pr = $prs[$l->requisition_id];
            $q = $quotes[$l->id] ?? null;
            $last = $q ? ($latest[$q->id] ?? null) : null;

            return $this->lineFacts($l, $pr->pr_no, $items[$l->item_id] ?? null) + [
                'req_type' => $pr->req_type, 'order_no' => $pr->order_id ? ($orders[$pr->order_id] ?? null) : null,
                'quotation' => $q ? ['qt_no' => $q->qt_no, 'status' => $q->status, 'revisable' => in_array($q->status, Quotation::REVISABLE, true), 'revisions' => $q->revisions, 'negotiation_message' => $q->negotiation_message, 'latest' => $last ? $this->presentRevision($last) : null] : null,
            ];
        })->values()->all();
    }

    /** A line as the portal shows it: "Cotton Jersey — Fabric · White · 160 GSM · 160 GSM combed — 120 KG · required 20 Nov 2026". @return array<string, mixed> */
    private function lineFacts(PurchaseRequisitionLine $l, ?string $prNo, ?MaterialItem $item): array
    {
        return [
            'line_id' => $l->id, 'pr_no' => $prNo, 'kind' => $l->kind, 'item_name' => $item?->name,
            'item_detail' => implode(' · ', array_filter([__('materialprocurement.pr.kind.'.$l->kind), $item?->colour, $item?->gsm ? "{$item->gsm} GSM" : null, $l->kind === 'TRIMS' ? $item?->size : null, $l->specification])),
            'specification' => $l->specification, 'colour_size' => implode(' / ', array_filter([$item?->colour, $item?->size])) ?: null,
            'req_qty' => (float) $l->req_qty, 'uom' => $l->uom, 'req_date' => $l->req_date->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function presentRevision(QuotationRevision $r): array
    {
        return [
            'revision' => $r->revision, 'quoted_qty' => (float) $r->quoted_qty, 'unit_price' => (float) $r->unit_price, 'currency' => $r->currency,
            'lead_time_days' => $r->lead_time_days, 'expected_delivery' => $r->expected_delivery->toDateString(), 'remarks' => $r->remarks, 'submitted_at' => $r->submitted_at->toIso8601String(),
        ];
    }
}
