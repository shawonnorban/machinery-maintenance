<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Reports\Procurement;

use App\Modules\Procurement\Models\FabricBooking;
use App\Modules\Reporting\Reports\Report;
use App\Modules\Reporting\Reports\ReportQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fabric held with a mill, and what became of it (Procurement spec §17).
 *
 * A booking is a promise made before there is an order, so the useful
 * column is not its status but whether it has turned into one. A booking
 * confirmed in March and never converted is cloth the factory believes it
 * has and the mill has long since sold.
 */
class FabricBookingStatusReport extends Report
{
    public function key(): string
    {
        return 'procurement_fabric_bookings';
    }

    public function group(): string
    {
        return 'procurement';
    }

    public function permission(): string
    {
        return 'procurement.booking.view_any';
    }

    public function filters(): array
    {
        return ['search', 'factory', 'status'];
    }

    public function columns(): array
    {
        return [
            'booking_no' => ['label' => 'report.columns.booking_no'],
            'booking_date' => ['label' => 'report.columns.booking_date'],
            'vendor' => ['label' => 'report.columns.vendor'],
            'required_date' => ['label' => 'report.columns.required_date'],
            'line_count' => ['label' => 'report.columns.lines', 'numeric' => true],
            'booked_value' => ['label' => 'report.columns.booked_value', 'numeric' => true],
            'status' => ['label' => 'report.columns.status'],
            'converted_to' => ['label' => 'report.columns.converted_to'],
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        foreach ($this->base($query)->lazy() as $booking) {
            $value = $booking->lines->reduce(
                fn (string $sum, $line): string => bcadd(
                    $sum,
                    bcmul((string) $line->booked_qty, (string) ($line->tentative_rate ?? '0'), 4),
                    4,
                ),
                '0.0000',
            );

            yield [
                'booking_no' => $booking->booking_no,
                'booking_date' => $booking->booking_date?->toDateString(),
                'vendor' => $booking->vendor?->name,
                'required_date' => $booking->required_date?->toDateString(),
                'line_count' => $booking->lines->count(),
                'booked_value' => $value,
                'status' => __("procurement.booking_statuses.{$booking->status}"),
                // The question the status cannot answer on its own.
                'converted_to' => $booking->purchaseRequisition?->pr_no,
            ];
        }
    }

    protected function countable(ReportQuery $query): Builder
    {
        return $this->base($query);
    }

    private function base(ReportQuery $query): Builder
    {
        $builder = FabricBooking::query()
            ->with(['vendor:id,name', 'lines', 'purchaseRequisition:id,pr_no']);

        if ($query->factoryId !== null) {
            $builder->where('factory_id', $query->factoryId);
        }

        if (is_string($status = $query->get('status')) && $status !== '') {
            $builder->where('status', $status);
        }

        if (is_string($search = $query->get('search')) && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $builder->where(fn ($q) => $q->whereRaw('LOWER(booking_no) LIKE ?', [$needle])
                ->orWhereHas('vendor', fn ($v) => $v->whereRaw('LOWER(name) LIKE ?', [$needle])));
        }

        return $builder->orderByDesc('booking_date');
    }
}
