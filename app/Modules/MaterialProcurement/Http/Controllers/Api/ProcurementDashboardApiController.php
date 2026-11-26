<?php

declare(strict_types=1);

namespace App\Modules\MaterialProcurement\Http\Controllers\Api;

use App\Modules\MaterialProcurement\Models\Followup;
use App\Modules\MaterialProcurement\Models\MaterialItem;
use App\Modules\MaterialProcurement\Models\MaterialSupplier;
use App\Modules\MaterialProcurement\Models\PurchaseRequisition;
use App\Modules\MaterialProcurement\Models\PurchaseRequisitionLine;
use App\Modules\MaterialProcurement\Models\Quotation;
use App\Modules\MaterialProcurement\Models\QuotationRevision;
use App\Modules\MaterialProcurement\Models\SupplierLogin;
use App\Modules\Merchandise\Models\Order;
use App\Shared\Http\Api\ApiController;
use App\Shared\Http\Api\ApiException;
use App\Shared\Http\Api\ApiResponse;
use App\Shared\Http\Api\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Material Procurement → Dashboard → Procurement Dashboard (AnnotechERP
 * 08/02): requisitions, quotations, approvals and deliveries at a glance —
 * eight counts, the latest requisitions and the deliveries being followed
 * up — under the workflow from master data to GRN.
 */
class ProcurementDashboardApiController extends ApiController
{
    /** How many latest requisitions and followed-up deliveries the cards show. */
    private const SHOWN = 8;

    /** Done deliveries: nothing more is awaited. */
    private const DONE = ['FULLY_RECEIVED', 'IN_HOUSE'];

    /** On its way or partly in: "In transit / receiving". */
    private const MOVING = ['DISPATCHED', 'IN_TRANSIT', 'PARTIALLY_RECEIVED'];

    public function index(): JsonResponse
    {
        $this->allowStaff();
        $openLines = PurchaseRequisitionLine::query()->where('mp_requisition_lines.status', 'OPEN_FOR_QUOTATION')
            ->join('mp_requisitions', 'mp_requisitions.id', '=', 'mp_requisition_lines.requisition_id')->where('mp_requisitions.status', 'SUBMITTED');
        $onOpen = fn (array $statuses): int => Quotation::query()->whereIn('status', $statuses)
            ->whereIn('requisition_line_id', (clone $openLines)->select('mp_requisition_lines.id'))->count();
        $activeSuppliers = MaterialSupplier::query()->where('active', true);

        return ApiResponse::ok([
            'counts' => [
                'draft' => PurchaseRequisition::query()->where('status', 'DRAFT')->count(),
                'submitted' => PurchaseRequisition::query()->where('status', '!=', 'DRAFT')->count(),
                'open_lines' => (clone $openLines)->count(),
                'quotations' => Quotation::query()->count(),
                'awaiting_review' => $onOpen(['PENDING', 'REVISED']),
                'negotiation' => $onOpen(['NEGOTIATION']),
                'approved_lines' => PurchaseRequisitionLine::query()->whereIn('status', PurchaseRequisitionLine::DELIVERY)->count(),
                'moving' => PurchaseRequisitionLine::query()->whereIn('status', self::MOVING)->count(),
            ],
            'active_items' => MaterialItem::query()->where('active', true)->count(),
            'active_suppliers' => (clone $activeSuppliers)->count(),
            'suppliers_with_login' => (clone $activeSuppliers)->whereIn('id', SupplierLogin::query()->where('active', true)->select('supplier_id'))->count(),
            'requisitions' => $this->latestRequisitions(),
            'deliveries' => $this->deliveries(),
            'done' => PurchaseRequisitionLine::query()->whereIn('status', self::DONE)->count(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function latestRequisitions(): array
    {
        $rows = PurchaseRequisition::query()->orderByDesc('pr_no')->limit(self::SHOWN)->get();
        $orders = Order::query()->whereIn('id', $rows->pluck('order_id')->filter())->pluck('order_no', 'id');
        $lines = PurchaseRequisitionLine::query()->whereIn('requisition_id', $rows->pluck('id'))
            ->groupBy('requisition_id')->select('requisition_id', DB::raw('count(*) as n'))->pluck('n', 'requisition_id');

        return $rows->map(fn (PurchaseRequisition $r): array => [
            'pr_no' => $r->pr_no, 'req_type' => $r->req_type, 'order_no' => $r->order_id ? ($orders[$r->order_id] ?? null) : null,
            'lines' => (int) ($lines[$r->id] ?? 0), 'req_date' => $r->req_date->toDateString(), 'status' => $r->status,
        ])->values()->all();
    }

    /** Approved lines, the ones still awaited first, most recently moved first. @return list<array<string, mixed>> */
    private function deliveries(): array
    {
        $rows = PurchaseRequisitionLine::query()->whereIn('status', PurchaseRequisitionLine::DELIVERY)
            ->orderByRaw('case when status in (?, ?) then 1 else 0 end', self::DONE)->orderByDesc('updated_at')->limit(self::SHOWN)->get();
        $items = MaterialItem::query()->whereIn('id', $rows->pluck('item_id'))->pluck('name', 'id');
        $suppliers = MaterialSupplier::query()->whereIn('id', $rows->pluck('approved_supplier_id'))->pluck('name', 'id');
        $prices = QuotationRevision::query()->whereIn('quotation_id', $rows->pluck('approved_quotation_id'))->orderBy('revision')->get()->groupBy('quotation_id')->map->last();
        $received = Followup::query()->whereIn('requisition_line_id', $rows->pluck('id'))->groupBy('requisition_line_id')
            ->select('requisition_line_id', DB::raw('sum(received_qty) as q'))->pluck('q', 'requisition_line_id');

        return $rows->map(function (PurchaseRequisitionLine $l) use ($items, $suppliers, $prices, $received): array {
            $price = $prices[$l->approved_quotation_id] ?? null;

            return [
                'line_id' => $l->id, 'item_name' => $items[$l->item_id] ?? null, 'supplier' => $suppliers[$l->approved_supplier_id] ?? null,
                'price' => $price ? ['currency' => $price->currency, 'unit_price' => (float) $price->unit_price] : null,
                'balance' => round(max(0, (float) $l->req_qty - (float) ($received[$l->id] ?? 0)), 3), 'uom' => $l->uom, 'status' => $l->status,
            ];
        })->values()->all();
    }

    /** Whoever works in the module; a supplier's portal login has its own dashboard. */
    private function allowStaff(): void
    {
        foreach (ProcurementControlApiController::STAFF as $permission) {
            if ($this->caller()->can($permission)) {
                return;
            }
        }
        throw ApiException::of(ErrorCode::FORBIDDEN);
    }
}
