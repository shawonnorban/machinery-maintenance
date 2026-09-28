<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderLine;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What has been ordered and not yet arrived (Procurement spec §17).
 *
 * Read from **lines**, not orders: an order half received is not pending
 * and not complete, and rolling it up to the header would force a choice
 * between overstating what is still coming and hiding it. The line knows
 * exactly how much is outstanding, so the line is the row.
 *
 * Overdue is measured against the line's own required date where it has
 * one, falling back to the order's delivery date. A line with neither is
 * reported without a verdict rather than as on time — nothing was
 * promised, so nothing is late.
 */
class PendingOrdersReport extends Report
{
    /** Orders that can still deliver. */
    private const OPEN_STATUSES = ['APPROVED', 'PARTIALLY_RECEIVED'];

    public function key(): string
    {
        return 'procurement_pending_orders';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.order.view_any';
    }

    public function filters(): array
    {
        return ['search', 'factory'];
    }

    public function columns(): array
    {
        return [
            'po_no' => ['label' => 'report.columns.po_no'],
            'po_date' => ['label' => 'report.columns.po_date'],
            'vendor' => ['label' => 'report.columns.vendor'],
            'item_code' => ['label' => 'report.columns.item_code'],
            'item_name' => ['label' => 'report.columns.name'],
            'ordered_qty' => ['label' => 'report.columns.ordered_qty', 'numeric' => true],
            'received_qty' => ['label' => 'report.columns.received_qty', 'numeric' => true],
            'pending_qty' => ['label' => 'report.columns.pending_qty', 'numeric' => true],
            'pending_value' => ['label' => 'report.columns.pending_value', 'numeric' => true],
            'due_date' => ['label' => 'report.columns.due_date'],
            'days_overdue' => ['label' => 'report.columns.days_overdue', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $today = CarbonImmutable::today();

        foreach ($this->base($query)->lazy() as $line) {
            $pending = $line->pendingQty();

            if (bccomp($pending, '0', 4) !== 1) {
                continue;
            }

            // The line's own date first: a single order often carries
            // several delivery promises.
            $due = $line->required_date ?? $line->order?->delivery_date;

            yield [
                'po_no' => $line->order?->po_no,
                'po_date' => $line->order?->po_date?->toDateString(),
                'vendor' => $line->order?->vendor?->name,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'ordered_qty' => (string) $line->ordered_qty,
                'received_qty' => (string) $line->received_qty,
                'pending_qty' => $pending,
                'pending_value' => bcmul($pending, (string) $line->rate, 4),
                'due_date' => $due?->toDateString(),
                // Null rather than zero when nothing was promised.
                'days_overdue' => $due === null
                    ? null
                    : max(0, (int) $due->startOfDay()->diffInDays($today, false)),
                'status' => __("procurement.statuses.{$line->order?->status}"),
            ];
        }
    }

    /** Open lines, which is the ceiling; how many are outstanding is the report. */
    public function estimatedRows(ReportQuery $query): int
    {
        return $this->base($query)->count();
    }

    private function base(ReportQuery $query): Builder
    {
        $orders = PurchaseOrder::query()->whereIn('status', self::OPEN_STATUSES);

        if ($query->factoryId !== null) {
            $orders->where('factory_id', $query->factoryId);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $orders->where(fn ($q) => $q->whereRaw('LOWER(po_no) LIKE ?', [$needle])
                ->orWhereHas('vendor', fn ($v) => $v->whereRaw('LOWER(name) LIKE ?', [$needle])));
        }

        return PurchaseOrderLine::query()
            ->with(['order:id,po_no,po_date,status,delivery_date,vendor_id', 'order.vendor:id,name'])
            ->whereIn('purchase_order_id', $orders->select('id'))
            ->whereColumn('received_qty', '<', 'ordered_qty')
            ->orderBy('purchase_order_id')
            ->orderBy('item_code');
    }
}
