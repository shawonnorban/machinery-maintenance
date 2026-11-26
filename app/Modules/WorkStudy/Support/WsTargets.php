<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Support;

use App\Modules\Planning\Models\PlanningLine;
use App\Modules\WorkStudy\Models\WsLineAllocation;
use App\Modules\WorkStudy\Models\WsNptEntry;
use App\Modules\WorkStudy\Models\WsSewingTarget;
use Illuminate\Support\Collection;

/**
 * What Work Study → Target & Feeding (AnnotechERP 14/03) shares: the target
 * a line's manpower, hours and efficiency are worth, the target sheet with a
 * spare row on every line, a line's allocations and how each stands, and its
 * NPT rows — with Brickland's lists of stoppage causes and departments.
 */
final class WsTargets
{
    /** NPT's causes, as Brickland lists them. */
    public const REASONS = ['Cutting Shortage', 'Trims / Accessories Shortage', 'Fabric Shortage', 'Machine Breakdown', 'Power Failure', 'Quality Hold', 'Style Change Over', 'Size Set / Piping', 'Manpower Shortage', 'Sample Making', 'Meeting / Training', 'Other'];

    /** The departments an NPT row is answered for by. */
    public const NPT_DEPTS = ['Industrial Engineering', 'Production', 'Quality', 'Maintenance', 'Planning'];

    /** The hours of the working day an NPT row may hit. */
    public const HOUR_SLOTS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];

    public const STATUSES = ['OPEN', 'RUNNING', 'CLOSED'];

    /** Blank NPT rows the sheet always offers under the saved ones. */
    public const NPT_SPARE = 5;

    /** Blank allocation rows Production Entry offers under a line's plans. */
    public const ALLOCATION_SPARE = 3;

    /** Target[Gen Hr]: (Used OP + ASO) × WH × 60 × T.Effi ÷ SMV, whole pieces — Brickland's 1,651 for 36 people, 10 h, 65 %, 8.5 SMV. */
    public static function targetGen(int $op, int $aso, float $wh, float $effi, float $smv): int
    {
        return $smv > 0 ? (int) floor(($op + $aso) * $wh * 60 * $effi / 100 / $smv + 1e-9) : 0;
    }

    /** A target row's standing in Records: off, achieved or behind. */
    public static function status(WsSewingTarget $t): string
    {
        if ($t->is_off) {
            return 'OFF';
        }

        return (int) $t->prod_gen + (int) $t->prod_ot >= (int) $t->target_gen + (int) $t->target_ot ? 'ACHIEVED' : 'BEHIND';
    }

    /** @return Collection<int, PlanningLine> */
    public static function lines(string $floorId): Collection
    {
        return PlanningLine::query()->where('floor_id', $floorId)->where('active', true)->orderBy('display_order')->orderBy('code')->get();
    }

    /**
     * The target sheet for a floor and day: every line's orders as set, and
     * one spare row on every line for another.
     *
     * @return list<array<string, mixed>>
     */
    public static function sheet(string $floorId, string $date): array
    {
        $saved = WsSewingTarget::query()->where('floor_id', $floorId)->whereDate('target_date', $date)->orderBy('sort_order')->orderBy('created_at')->get()->groupBy('planning_line_id');
        $rows = [];
        foreach (self::lines($floorId) as $line) {
            foreach ($saved[$line->id] ?? [] as $t) {
                $rows[] = self::row($t, $line);
            }
            $rows[] = ['id' => null, 'planning_line_id' => $line->id, 'line' => $line->code, 'spare' => true];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public static function row(WsSewingTarget $t, ?PlanningLine $line = null): array
    {
        $out = $t->only(['id', 'planning_line_id', 'buyer_id', 'order_style', 'gmt_item', 'fob', 'smv', 'is_car', 'day_run', 'lo_effi', 't_effi', 'wh', 'wh_from', 'wh_to',
            'used_op', 'used_aso', 'layout_op', 'layout_aso', 'wip_rfi', 'plan', 'target_gen', 'target_ot', 'prod_gen', 'prod_ot', 'input_min', 'prod_min', 'ttl_value', 'is_merged', 'is_single', 'is_off']);
        $out['ship_date'] = $t->ship_date?->toDateString();
        $out['line'] = $line?->code;
        $out['ttl_target'] = (int) $t->target_gen + (int) $t->target_ot;
        $out['ttl_prod'] = (int) $t->prod_gen + (int) $t->prod_ot;
        $out['spare'] = false;

        return $out;
    }

    /**
     * Production Entry: a line's allocations, with three spare rows for new
     * ones.
     *
     * @return list<array<string, mixed>>
     */
    public static function allocations(string $lineId, bool $spare = true): array
    {
        $rows = WsLineAllocation::query()->where('planning_line_id', $lineId)->orderByRaw('plan_start is null')->orderBy('plan_start')->orderBy('created_at')->get()
            ->map(fn (WsLineAllocation $a) => self::allocationRow($a))->all();
        if ($spare) {
            for ($i = 0; $i < self::ALLOCATION_SPARE; $i++) {
                $rows[] = ['id' => null, 'spare' => true];
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    public static function allocationRow(WsLineAllocation $a): array
    {
        $out = $a->only(['id', 'buyer_id', 'order_style', 'style', 'item', 'smv', 'allocation', 'production', 'in_target', 'status']);
        foreach (['input_date', 'plan_start', 'plan_end'] as $d) {
            $out[$d] = $a->{$d}?->toDateString();
        }
        // Achieved: what was made, as a share of the allocation, and what is left — Brickland's "970 · 19.4% [4030]".
        $out['achieved_pct'] = $a->allocation > 0 ? round($a->production / $a->allocation * 100, 1) : null;
        $out['remaining'] = max(0, (int) $a->allocation - (int) $a->production);
        $out['spare'] = false;

        return $out;
    }

    /**
     * NPT Entry: a line's stoppages on a day, and blank rows to book more.
     *
     * @return list<array<string, mixed>>
     */
    public static function npt(string $lineId, string $date): array
    {
        $rows = WsNptEntry::query()->where('planning_line_id', $lineId)->whereDate('npt_date', $date)->orderBy('created_at')->get()
            ->map(fn (WsNptEntry $n) => $n->only(['id', 'order_style', 'reason_type', 'reason_details', 'responsible_dept', 'hour_slots', 'manpower', 'minutes', 'npt']))->all();
        for ($i = 0; $i < self::NPT_SPARE; $i++) {
            $rows[] = ['id' => null, 'order_style' => null, 'reason_type' => null, 'reason_details' => null, 'responsible_dept' => null, 'hour_slots' => [], 'manpower' => null, 'minutes' => null, 'npt' => 0];
        }

        return $rows;
    }
}
