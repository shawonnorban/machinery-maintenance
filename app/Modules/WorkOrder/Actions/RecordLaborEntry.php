<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Actions;

use App\Modules\WorkOrder\Models\LaborRateGrade;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderLaborEntry;
use App\Modules\WorkOrder\Services\WorkOrderCostCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records time spent on a work order (ADR-050, ADR-065).
 *
 * The rate is resolved server-side from the technician's grade, effective on
 * the day the work happened, and copied onto the entry. A client-supplied rate
 * is ignored for internal labour: it would let anyone set what the work cost,
 * and it would leak what people are paid.
 */
class RecordLaborEntry
{
    public function __construct(private readonly WorkOrderCostCalculator $costs) {}

    public function handle(
        WorkOrder $workOrder,
        CarbonImmutable $startedAt,
        CarbonImmutable $endedAt,
        string $category = 'REGULAR',
        ?Technician $technician = null,
        ?string $vendorId = null,
        ?string $externalRate = null,
        ?string $notes = null,
        ?string $userId = null,
    ): WorkOrderLaborEntry {
        if (! in_array($category, WorkOrderLaborEntry::CATEGORIES, true)) {
            throw ValidationException::withMessages([
                'labor_category' => __('work_order.labor_category_unknown'),
            ]);
        }

        if ($workOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'work_order_id' => __('work_order.labor_after_close'),
            ])->status(409);
        }

        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            throw ValidationException::withMessages([
                'ended_at' => __('work_order.labor_end_before_start'),
            ]);
        }

        if ($endedAt->isFuture()) {
            throw ValidationException::withMessages([
                'ended_at' => __('work_order.labor_in_future'),
            ]);
        }

        $minutes = (int) round($startedAt->diffInMinutes($endedAt, absolute: true));

        if ($minutes > 24 * 60) {
            // A single entry longer than a day is a data-entry slip, not a
            // shift, and it would distort every technician KPI it touches.
            throw ValidationException::withMessages([
                'ended_at' => __('work_order.labor_too_long'),
            ]);
        }

        [$rate, $grade] = $this->resolveRate($category, $technician, $externalRate, $startedAt);

        if ($category !== 'EXTERNAL') {
            if ($technician === null) {
                throw ValidationException::withMessages([
                    'technician_id' => __('work_order.labor_needs_technician'),
                ]);
            }

            $this->assertNoOverlap($technician, $startedAt, $endedAt);
        }

        $amount = number_format(((float) $rate) * ($minutes / 60), 4, '.', '');

        return DB::transaction(function () use (
            $workOrder, $technician, $category, $grade, $vendorId,
            $startedAt, $endedAt, $minutes, $rate, $amount, $notes, $userId
        ): WorkOrderLaborEntry {
            $entry = WorkOrderLaborEntry::create([
                'work_order_id' => $workOrder->id,
                'technician_id' => $technician?->id,
                'labor_category' => $category,
                'labor_grade_id' => $grade?->id,
                'vendor_id' => $vendorId,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'minutes' => $minutes,
                'hourly_rate' => $rate,
                'currency' => $workOrder->currency,
                'exchange_rate' => '1',
                'amount' => $amount,
                'base_amount' => $amount,
                'notes' => $notes,
                'recorded_by' => $userId,
            ]);

            // Derived, never accepted from a client (ADR-064).
            $this->costs->recalculate($workOrder);

            return $entry;
        });
    }

    public function delete(WorkOrderLaborEntry $entry): void
    {
        $workOrder = WorkOrder::findOrFail($entry->work_order_id);

        if ($workOrder->isTerminal()) {
            throw ValidationException::withMessages([
                'work_order_id' => __('work_order.labor_after_close'),
            ])->status(409);
        }

        DB::transaction(function () use ($entry, $workOrder): void {
            $entry->delete();
            $this->costs->recalculate($workOrder);
        });
    }

    /**
     * @return array{0: string, 1: LaborRateGrade|null}
     */
    private function resolveRate(
        string $category,
        ?Technician $technician,
        ?string $externalRate,
        CarbonImmutable $on,
    ): array {
        if ($category === 'EXTERNAL') {
            if (blank($externalRate)) {
                throw ValidationException::withMessages([
                    'hourly_rate' => __('work_order.external_needs_rate'),
                ]);
            }

            // A contractor's charge is an invoiced amount, not employee
            // compensation, so it is supplied rather than looked up.
            return [number_format((float) $externalRate, 4, '.', ''), null];
        }

        $grade = $technician?->labor_grade_id !== null
            ? LaborRateGrade::whereKey($technician->labor_grade_id)->effectiveOn($on)->first()
            : null;

        if ($grade === null) {
            throw ValidationException::withMessages([
                'technician_id' => __('work_order.technician_needs_grade'),
            ]);
        }

        return [$grade->rateFor($category), $grade];
    }

    /**
     * One technician cannot be in two places at once. Overlapping entries make
     * utilisation figures meaningless and are almost always a mistyped date.
     */
    private function assertNoOverlap(
        Technician $technician,
        CarbonImmutable $startedAt,
        CarbonImmutable $endedAt,
    ): void {
        $clash = WorkOrderLaborEntry::query()
            ->where('technician_id', $technician->id)
            ->where('started_at', '<', $endedAt)
            ->where('ended_at', '>', $startedAt)
            ->first();

        if ($clash !== null) {
            throw ValidationException::withMessages([
                'started_at' => __('work_order.labor_overlap', [
                    'from' => $clash->started_at->toDateTimeString(),
                    'to' => $clash->ended_at->toDateTimeString(),
                ]),
            ])->status(409);
        }
    }
}
