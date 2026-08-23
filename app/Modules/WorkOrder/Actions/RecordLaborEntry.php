<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Actions;

use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderLaborEntry;
use App\Modules\WorkOrder\Services\WorkOrderCostCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records time spent on a work order (ADR-050).
 *
 * Time, and nothing but time. Technicians are salaried employees, so their
 * hours carry no cost of their own — the work is paid for whether it happens
 * on a dyeing machine or not. What the hours answer is workload and technician
 * performance: who did the work, and how long it took.
 *
 * Money that genuinely leaves the business for a repair — parts, a contractor's
 * invoice, a vendor's charge — is a cost entry in its own right, recorded
 * where the invoice is.
 */
class RecordLaborEntry
{
    public function __construct(private readonly WorkOrderCostCalculator $costs) {}

    public function handle(
        WorkOrder $workOrder,
        CarbonImmutable $startedAt,
        CarbonImmutable $endedAt,
        ?Technician $technician = null,
        ?string $notes = null,
        ?string $userId = null,
    ): WorkOrderLaborEntry {
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

        if ($technician === null) {
            throw ValidationException::withMessages([
                'technician_id' => __('work_order.labor_needs_technician'),
            ]);
        }

        $this->assertNoOverlap($technician, $startedAt, $endedAt);

        return DB::transaction(function () use (
            $workOrder, $technician, $startedAt, $endedAt, $minutes, $notes, $userId
        ): WorkOrderLaborEntry {
            $entry = WorkOrderLaborEntry::create([
                'work_order_id' => $workOrder->id,
                'technician_id' => $technician->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'minutes' => $minutes,
                'notes' => $notes,
                'recorded_by' => $userId,
            ]);

            // The work order's own totals still come from parts and posted
            // costs, so they are recomputed here as well (ADR-064).
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
