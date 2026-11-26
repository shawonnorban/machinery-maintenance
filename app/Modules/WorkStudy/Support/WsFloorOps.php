<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Support;

use App\Modules\Planning\Models\PlanningLine;
use App\Modules\WorkStudy\Models\WsManMinuteAdjustment;
use App\Modules\WorkStudy\Models\WsOutputAdjustment;
use App\Modules\WorkStudy\Models\WsWashRequisition;
use Illuminate\Support\Carbon;

/**
 * What Work Study → Floor Operations (AnnotechERP 14/07) shares: the hour
 * sheet of a floor's sewing output corrections, a line's man-minute sheet by
 * reason, the wash requisition's steps and its number — with Brickland's
 * list of reasons a line's people were not sewing its order.
 */
final class WsFloorOps
{
    /** The hours of the working day the output sheet corrects, as Brickland picks them. */
    public const HOURS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

    /** Why a line's people were not sewing its order, as Brickland lists them. */
    public const MM_REASONS = [
        'Manpower Used In Others', 'Alter Rectify', 'Piping Cut', 'Special Order Sewing', 'Trans 2 OtherSec', 'Training Center', 'Process DueTo Trims/Accs',
        'Instant Leave', 'Sample Making', 'HR Suspension', 'Alter Rectify After S/C', 'Emergency Shipment', 'Trans Recv From O.Sec', 'Working on Size Set & Piping', 'Working as Supervisor',
    ];

    public const WASH_STATUSES = ['DRAFT_CREATED', 'SUBMITTED', 'VERIFIED', 'COMPLETE', 'CANCELLED'];

    /** Take Action: each action and the step it moves a requisition to. */
    public const WASH_ACTIONS = ['create_draft' => 'DRAFT_CREATED', 'submit' => 'SUBMITTED', 'verify' => 'VERIFIED', 'complete' => 'COMPLETE', 'cancel' => 'CANCELLED'];

    /** Neither complete nor cancelled. */
    public const WASH_OPEN = ['DRAFT_CREATED', 'SUBMITTED', 'VERIFIED'];

    /**
     * The output the system recorded for a line in an hour. Nothing records
     * hourly sewing output yet (the garments production module will), so it
     * is nothing — as on Brickland's demo, whose Recorded column reads 0.
     */
    public static function recorded(string $lineId, string $date, int $hour): int
    {
        return 0;
    }

    /** Increased / Decreased / Unchanged: where the correction left the hour. */
    public static function direction(int $recorded, int $adjusted): string
    {
        return $adjusted > $recorded ? 'INCREASED' : ($adjusted < $recorded ? 'DECREASED' : 'UNCHANGED');
    }

    /**
     * Every active line on a floor for one hour of a day: what was recorded,
     * what it is corrected to (the recorded figure where nothing is), and the
     * buyer, order and item a correction names.
     *
     * @return list<array<string, mixed>>
     */
    public static function outputSheet(string $floorId, string $date, int $hour): array
    {
        $saved = WsOutputAdjustment::query()->where('floor_id', $floorId)->whereDate('adjust_date', $date)->where('hour', $hour)->whereNotNull('planning_line_id')->get()->keyBy('planning_line_id');

        return self::lines($floorId)->map(function (PlanningLine $l) use ($saved, $date, $hour): array {
            $a = $saved->get($l->id);
            $recorded = $a ? $a->recorded_qty : self::recorded($l->id, $date, $hour);

            return [
                'id' => $a?->id, 'planning_line_id' => $l->id, 'line' => $l->code,
                'buyer_id' => $a?->buyer_id, 'order_no' => $a?->order_no, 'item_name' => $a?->item_name,
                'recorded' => $recorded, 'prod' => $a ? $a->adjusted_qty : $recorded,
            ];
        })->values()->all();
    }

    /**
     * Every reason for a line on a day: the general-shift operators and ASOs,
     * each overtime hour's, and Man OT added up across the overtime hours.
     *
     * @return list<array<string, mixed>>
     */
    public static function manMinuteSheet(string $lineId, string $date): array
    {
        $saved = WsManMinuteAdjustment::query()->where('planning_line_id', $lineId)->whereDate('adjust_date', $date)->get()->keyBy('reason');

        return array_map(function (string $reason) use ($saved): array {
            $a = $saved->get($reason);
            $slots = [];
            foreach (array_keys(WsOvertime::SLOTS) as $slot) {
                $slots[(string) $slot] = ['op' => (int) ($a?->ot_slots[(string) $slot]['op'] ?? 0), 'aso' => (int) ($a?->ot_slots[(string) $slot]['aso'] ?? 0)];
            }

            return [
                'id' => $a?->id, 'reason' => $reason, 'used_op' => (int) ($a?->used_op ?? 0), 'used_aso' => (int) ($a?->used_aso ?? 0),
                'ot_op' => (int) ($a?->ot_op ?? 0), 'ot_aso' => (int) ($a?->ot_aso ?? 0), 'slots' => $slots,
            ];
        }, self::MM_REASONS);
    }

    /**
     * Overtime hours as stored (only hours someone was booked away in) and Man OT for them.
     *
     * @param  array<int|string, array{op?: mixed, aso?: mixed}>  $slots
     * @return array{0: array<string, array{op: int, aso: int}>, 1: int, 2: int}
     */
    public static function otTotals(array $slots): array
    {
        $clean = WsOvertime::clean($slots);

        return [$clean, array_sum(array_column($clean, 'op')), array_sum(array_column($clean, 'aso'))];
    }

    /** The next requisition number for the month it is raised in: GMT-WR-2026100001. */
    public static function nextRequisitionNo(string $date): string
    {
        $head = 'GMT-WR-'.Carbon::parse($date)->format('Ym');
        $last = WsWashRequisition::query()->where('requisition_no', 'like', $head.'%')->lockForUpdate()->pluck('requisition_no')
            ->map(fn (string $no): int => (int) substr($no, strlen($head)))->max();

        return $head.str_pad((string) (1 + (int) $last), 4, '0', STR_PAD_LEFT);
    }

    /** @return \Illuminate\Support\Collection<int, PlanningLine> */
    private static function lines(string $floorId)
    {
        return PlanningLine::query()->where('floor_id', $floorId)->where('active', true)->orderBy('display_order')->orderBy('code')->get();
    }
}
