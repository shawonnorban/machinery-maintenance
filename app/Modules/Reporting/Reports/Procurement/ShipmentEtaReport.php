<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\Shipment;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * What is on the water and when it lands (Procurement spec §17).
 *
 * Carries the original ETA beside the current one, which is the whole
 * point: the gap between what a forwarder first promised and what
 * happened is the supplier's delivery record, and a report showing only
 * today's forecast has quietly thrown that away.
 */
class ShipmentEtaReport extends Report
{
    public function key(): string
    {
        return 'procurement_shipment_eta';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.import.view_any';
    }

    public function filters(): array
    {
        return ['search', 'status'];
    }

    public function columns(): array
    {
        return [
            'shipment_no' => ['label' => 'report.columns.shipment_no'],
            'po_no' => ['label' => 'report.columns.po_no'],
            'vendor' => ['label' => 'report.columns.vendor'],
            'mode' => ['label' => 'report.columns.mode'],
            'bl_awb_no' => ['label' => 'report.columns.bl_awb_no'],
            'shipping_date' => ['label' => 'report.columns.shipped_on'],
            'original_eta' => ['label' => 'report.columns.original_eta'],
            'eta' => ['label' => 'report.columns.eta'],
            'days_to_eta' => ['label' => 'report.columns.days_to_eta', 'numeric' => true],
            'actual_arrival_date' => ['label' => 'report.columns.arrived_on'],
            'delay_days' => ['label' => 'report.columns.delay_days', 'numeric' => true],
            'document_status' => ['label' => 'report.columns.document_status'],
            'status' => ['label' => 'report.columns.status'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $shipment) {
            yield [
                'shipment_no' => $shipment->shipment_no,
                'po_no' => $shipment->purchaseOrder?->po_no,
                'vendor' => $shipment->vendor?->name,
                'mode' => __("procurement.shipment_modes.{$shipment->mode}"),
                'bl_awb_no' => $shipment->bl_awb_no,
                'shipping_date' => $shipment->shipping_date?->toDateString(),
                'original_eta' => $shipment->original_eta?->toDateString(),
                'eta' => $shipment->eta?->toDateString(),
                'days_to_eta' => $shipment->daysToEta(),
                'actual_arrival_date' => $shipment->actual_arrival_date?->toDateString(),
                // Against what was first promised, not the latest guess.
                'delay_days' => $shipment->delayDays(),
                'document_status' => __("procurement.document_statuses.{$shipment->document_status}"),
                'status' => __("procurement.shipment_statuses.{$shipment->status}"),
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = Shipment::query()->with(['vendor:id,name', 'purchaseOrder:id,po_no']);

        if (is_string($status = $query->get('status')) && $status !== '') {
            $builder->where('status', $status);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $builder->where(fn ($q) => $q->whereRaw('LOWER(shipment_no) LIKE ?', [$needle])
                ->orWhereRaw("LOWER(COALESCE(bl_awb_no, '')) LIKE ?", [$needle])
                ->orWhereRaw("LOWER(COALESCE(container_no, '')) LIKE ?", [$needle]));
        }

        return $builder->orderByRaw('eta IS NULL, eta ASC');
    }
}
