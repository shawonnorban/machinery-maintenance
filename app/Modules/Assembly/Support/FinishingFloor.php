<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Assembly Floor → Finishing (AnnotechERP 20/02), what both desks and the
 * dashboard share: the finishing lines (Company Management's lines of type
 * FINISHING, each filed under a section and a floor of a unit), a line's
 * target and hours, the shifts a scan's clock time falls in, and the order's
 * locked master plan the desks show beside a scanned bundle.
 */
final class FinishingFloor
{
    /** Where a bundle stands on its way into and through finishing. */
    public const STATUSES = ['NOT_READY', 'READY', 'IN_FINISHING', 'FINISHING_OUT'];

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now('Asia/Dhaka')->startOfDay();
    }

    /** Finishing lines (or Quality's, with $type QUALITY — 20/03) by id: code, name, section, floor, unit, manpower and daily target. */
    public static function lines(string $companyId, string $type = 'FINISHING'): Collection
    {
        return DB::table('production_lines as l')
            ->leftJoin('sections as s', 's.id', '=', 'l.section_id')
            ->leftJoin('floors as f', 'f.id', '=', 'l.floor_id')
            ->leftJoin('buildings as b', 'b.id', '=', 'f.building_id')
            ->leftJoin('factories as u', 'u.id', '=', 'b.factory_id')
            ->where('l.company_id', $companyId)->where('l.line_type', $type)
            ->orderBy('s.name')->orderBy('f.name')->orderBy('l.code')
            ->get(['l.id', 'l.code', 'l.name', 'l.status', 'l.manpower', 'l.target_per_day', 'l.section_id', 's.name as section', 'l.floor_id', 'f.name as floor_name', 'f.code as floor_code', 'u.id as unit_id', 'u.name as unit'])
            ->map(function ($l) {
                $l->active = $l->status === 'ACTIVE';
                $l->floor = $l->floor_name ? trim($l->floor_name.($l->floor_code ? ' · '.$l->floor_code : '')) : null;
                $l->label = self::lineLabel($l);

                return $l;
            })->keyBy('id');
    }

    public static function lineLabel(?object $line): ?string
    {
        return $line ? trim($line->code.' — '.$line->name) : null;
    }

    /**
     * The shift plan of production lines: Capacity Planning → Shift Master
     * (`planning_line_shifts`) rows of the planning line laid over the floor
     * line, each on an HR & Payroll shift.
     */
    private static function linePlan()
    {
        return DB::table('planning_line_shifts as ls')
            ->join('planning_lines as pl', 'pl.id', '=', 'ls.planning_line_id')
            ->join('hr_shifts as s', 's.id', '=', 'ls.hr_shift_id')
            ->whereNotNull('pl.floor_line_id')
            ->where('ls.active', true);
    }

    /** A line's working hours from its shift plan (its first shift of the day), or null. */
    public static function hours(string $lineId): ?float
    {
        $shift = self::linePlan()->where('pl.floor_line_id', $lineId)->orderBy('s.start_time')->first(['s.start_time', 's.end_time']);
        if (! $shift) {
            return null;
        }
        $start = CarbonImmutable::parse('2000-01-01 '.$shift->start_time);
        $end = CarbonImmutable::parse('2000-01-01 '.$shift->end_time);
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }

    /** The lines a shift covers: those with it in their shift plan. */
    public static function shiftLines(string $shiftId, string $from, string $to): Collection
    {
        return self::linePlan()->where('ls.hr_shift_id', $shiftId)->distinct()->pluck('pl.floor_line_id');
    }

    /** Lines with any shift plan at all (a line without one works every shift). */
    public static function plannedLines(): Collection
    {
        return self::linePlan()->distinct()->pluck('pl.floor_line_id');
    }

    /** Whether a scan's clock time (Asia/Dhaka) falls within a shift. */
    public static function inShift(CarbonImmutable|\DateTimeInterface $at, object $shift): bool
    {
        $t = CarbonImmutable::instance($at)->setTimezone('Asia/Dhaka')->format('H:i:s');

        return ! empty($shift->cross_day) || $shift->end_time <= $shift->start_time
            ? ($t >= $shift->start_time || $t < $shift->end_time)
            : ($t >= $shift->start_time && $t < $shift->end_time);
    }

    /** The order's locked master plan (its latest version), or null. */
    public static function orderPlan(?string $orderId): ?object
    {
        return $orderId ? DB::table('planning_master_plans')->where('order_id', $orderId)->where('status', 'LOCKED')->orderByDesc('version')->orderByDesc('created_at')->first() : null;
    }
}
