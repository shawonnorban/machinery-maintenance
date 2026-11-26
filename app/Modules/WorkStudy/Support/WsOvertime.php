<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Support;

use App\Modules\Planning\Models\PlanningLine;
use App\Modules\WorkStudy\Models\WsOtPlan;
use Illuminate\Support\Facades\DB;

/**
 * What Work Study → OT & Salary (AnnotechERP 14/06) shares: the overtime
 * hours Brickland's plan carries, and a floor's manpower groups for a day.
 *
 * A group is a line, department and designation on the floor: every group
 * the floor's active employees are posted to (HR & Payroll), with its
 * headcount as MP (Gen.), and every group already planned that day — so a
 * plan made for a group nobody is posted to still shows.
 */
final class WsOvertime
{
    /** The hours the plan offers, by hour of the working day, as Brickland prints them: 9th(6PM) … 20th(5AM). */
    public const SLOTS = [9 => '6PM', 10 => '7PM', 11 => '8PM', 12 => '9PM', 13 => '10PM', 14 => '11PM', 15 => '12PM', 17 => '2AM', 20 => '5AM'];

    /** An hour's heading: 9th(6PM). */
    public static function slotLabel(int $slot): string
    {
        return "{$slot}th(".self::SLOTS[$slot].')';
    }

    /** The first `count` hours from 6PM. */
    public static function firstSlots(int $count): array
    {
        return array_slice(array_keys(self::SLOTS), 0, max(0, $count));
    }

    /**
     * Slots as stored: only hours with someone staying, each with its
     * operators and ASOs.
     *
     * @param  array<int|string, array{op?: mixed, aso?: mixed}>  $slots
     * @return array<string, array{op: int, aso: int}>
     */
    public static function clean(array $slots): array
    {
        $out = [];
        foreach (array_keys(self::SLOTS) as $slot) {
            $op = (int) ($slots[$slot]['op'] ?? $slots[(string) $slot]['op'] ?? 0);
            $aso = (int) ($slots[$slot]['aso'] ?? $slots[(string) $slot]['aso'] ?? 0);
            if ($op > 0 || $aso > 0) {
                $out[(string) $slot] = ['op' => $op, 'aso' => $aso];
            }
        }

        return $out;
    }

    /** How many hours a plan has someone staying to. */
    public static function hours(?array $slots): int
    {
        return count(self::clean($slots ?? []));
    }

    /** A group's key: line|department|designation. */
    public static function key(?string $line, ?string $department, ?string $designation): string
    {
        return ($line ?? '').'|'.($department ?? '').'|'.($designation ?? '');
    }

    /**
     * The OT Plan sheet for a floor and day: every manpower group, as planned
     * or as posted.
     *
     * @return list<array<string, mixed>>
     */
    public static function groups(string $floorId, string $date): array
    {
        $company = DB::table('floors')->where('id', $floorId)->value('company_id');
        $lines = PlanningLine::query()->where('floor_id', $floorId)->get();
        $byFloorLine = $lines->whereNotNull('floor_line_id')->keyBy('floor_line_id');
        $posted = DB::table('hr_employees')->where('company_id', $company)->where('floor_id', $floorId)->where('status', 'ACTIVE')
            ->selectRaw('department_id, line_id, designation_id, count(*) as heads')->groupBy('department_id', 'line_id', 'designation_id')->get();
        $plans = WsOtPlan::query()->where('floor_id', $floorId)->whereDate('plan_date', $date)->get();
        $rows = [];
        foreach ($posted as $g) {
            $line = $g->line_id ? $byFloorLine->get($g->line_id)?->id : null;
            $rows[self::key($line, $g->department_id, $g->designation_id)] = ['planning_line_id' => $line, 'department_id' => $g->department_id, 'designation_id' => $g->designation_id, 'heads' => (int) $g->heads];
        }
        foreach ($plans as $p) {
            $k = self::key($p->planning_line_id, $p->department_id, $p->designation_id);
            $rows[$k] = ($rows[$k] ?? ['planning_line_id' => $p->planning_line_id, 'department_id' => $p->department_id, 'designation_id' => $p->designation_id, 'heads' => null]) + ['plan' => $p];
        }
        $departments = DB::table('departments')->whereIn('id', array_filter(array_column($rows, 'department_id')))->pluck('name', 'id');
        $designations = DB::table('designations')->whereIn('id', array_filter(array_column($rows, 'designation_id')))->pluck('name', 'id');
        $codes = $lines->pluck('code', 'id');
        $out = [];
        foreach ($rows as $r) {
            $p = $r['plan'] ?? null;
            // prevWH: the group's general hours on its last planned day before this one.
            $prev = $p?->prev_wh ?? WsOtPlan::query()->where('floor_id', $floorId)->whereDate('plan_date', '<', $date)
                ->where(fn ($q) => $r['planning_line_id'] ? $q->where('planning_line_id', $r['planning_line_id']) : $q->whereNull('planning_line_id'))
                ->where(fn ($q) => $r['department_id'] ? $q->where('department_id', $r['department_id']) : $q->whereNull('department_id'))
                ->where(fn ($q) => $r['designation_id'] ? $q->where('designation_id', $r['designation_id']) : $q->whereNull('designation_id'))
                ->orderByDesc('plan_date')->value('wh_gen');
            $out[] = [
                'id' => $p?->id,
                'planning_line_id' => $r['planning_line_id'], 'line' => $r['planning_line_id'] ? ($codes[$r['planning_line_id']] ?? null) : null,
                'department_id' => $r['department_id'], 'department' => $r['department_id'] ? ($departments[$r['department_id']] ?? null) : null,
                'designation_id' => $r['designation_id'], 'designation' => $r['designation_id'] ? ($designations[$r['designation_id']] ?? null) : null,
                'mp_gen' => $p?->mp_gen ?? $r['heads'], 'prev_wh' => $prev === null ? null : (float) $prev, 'wh_gen' => $p?->wh_gen === null ? null : (float) $p->wh_gen,
                'slots' => self::clean($p?->slots ?? []), 'remarks' => $p?->remarks,
            ];
        }
        usort($out, fn ($a, $b) => [$a['department'] ?? '', $a['line'] ?? '', $a['designation'] ?? ''] <=> [$b['department'] ?? '', $b['line'] ?? '', $b['designation'] ?? '']);

        return $out;
    }
}
