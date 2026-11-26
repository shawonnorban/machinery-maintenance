<?php

declare(strict_types=1);

namespace App\Modules\QualityTally\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quality Tally's rules (AnnotechERP 36, the formulas from annotech-rmg's
 * TallyMetrics, kept exactly):
 *
 *  - checked counts RFT and DEFECT only. Rectify and reject are not
 *    inspections of new garments, so counting them would inflate output.
 *  - pass = RFT + RECTIFY, CAPPED at checked: a line that rectifies more than
 *    it inspected must not report more passes than garments.
 *  - DHU = defect TAGS ÷ checked × 100 — tags, not defect taps: one garment can
 *    carry three faults. Null (not 0) when nothing was checked.
 *  - the four actions are independent; rectify and reject do not offset defect.
 *
 * Lines: sewing = Capacity Planning's planning lines (the lines Assembly and
 * Production Tracking follow); cutting and quality = Company Management's
 * production lines of type CUTTING / QUALITY.
 */
final class QtBook
{
    public const TZ = 'Asia/Dhaka';

    public const SECTIONS = ['CUTTING', 'SEWING', 'QUALITY'];

    public const ACTIONS = ['RFT', 'DEFECT', 'RECTIFY', 'REJECT'];

    /** How far back an offline tap may still be synced. */
    public const LATE_DAYS = 3;

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ);
    }

    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /**
     * The lines of a section: id => {id, code, name, floor, active, section}.
     *
     * @return Collection<string, object>
     */
    public static function lines(string $companyId, string $section): Collection
    {
        if ($section === 'SEWING') {
            return DB::table('planning_lines as l')->leftJoin('floors as f', 'f.id', '=', 'l.floor_id')
                ->where('l.company_id', $companyId)->orderBy('l.display_order')->orderBy('l.code')
                ->get(['l.id', 'l.code', 'l.name', 'f.name as floor', 'l.active'])
                ->map(fn ($l) => (object) ['id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'floor' => $l->floor, 'active' => (bool) $l->active, 'section' => 'SEWING'])
                ->keyBy('id');
        }

        return DB::table('production_lines as l')->leftJoin('floors as f', 'f.id', '=', 'l.floor_id')
            ->where('l.company_id', $companyId)->where('l.line_type', $section)
            ->orderBy('l.code')->get(['l.id', 'l.code', 'l.name', 'f.name as floor', 'l.status'])
            ->map(fn ($l) => (object) ['id' => $l->id, 'code' => $l->code, 'name' => $l->name, 'floor' => $l->floor, 'active' => $l->status === 'ACTIVE', 'section' => $section])
            ->keyBy('id');
    }

    /** Every section's lines, keyed "SECTION:id". @return Collection<string, object> */
    public static function allLines(string $companyId): Collection
    {
        $all = collect();
        foreach (self::SECTIONS as $section) {
            foreach (self::lines($companyId, $section) as $line) {
                $all->put($section.':'.$line->id, $line);
            }
        }

        return $all;
    }

    /** "LINE01 — Line 01" */
    public static function label(?object $line): ?string
    {
        if ($line === null) {
            return null;
        }

        return $line->code === $line->name ? $line->code : trim($line->code.' — '.$line->name);
    }

    /* ── Shifts (HR & Payroll's, one master per list) ───────────────── */

    /**
     * The company's active HR shifts: id => {id, code, name, start, end, cross_day, minutes}.
     *
     * @return Collection<string, object>
     */
    public static function shifts(string $companyId): Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('hr_shifts')) {
            return collect();
        }

        return DB::table('hr_shifts')->where('company_id', $companyId)->where('active', true)->orderBy('start_time')
            ->get(['id', 'code', 'name', 'start_time', 'end_time', 'cross_day', 'break_min', 'working_hours'])
            ->map(fn ($s) => (object) [
                'id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'start' => $s->start_time, 'end' => $s->end_time,
                'cross_day' => (bool) $s->cross_day || $s->end_time <= $s->start_time,
                'minutes' => self::shiftMinutes($s->start_time, $s->end_time, $s->working_hours === null ? null : (float) $s->working_hours, (int) ($s->break_min ?? 0)),
            ])->keyBy('id');
    }

    /** Paid minutes of a shift: its working hours when HR set them, else end − start − break. */
    public static function shiftMinutes(string $start, string $end, ?float $workingHours = null, int $breakMin = 0): int
    {
        if ($workingHours !== null && $workingHours > 0) {
            return (int) round($workingHours * 60);
        }
        $toMin = fn (string $t): int => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
        $span = $toMin($end) - $toMin($start);
        if ($span <= 0) {
            $span += 24 * 60;
        }

        return max(0, $span - $breakMin);
    }

    /**
     * The shift running at a moment (factory clock); between shifts, the one that
     * ended last — its overtime (17:00–20:00 is the day's, 05:00–08:00 the night's).
     */
    public static function shiftAt(Collection $shifts, CarbonImmutable $at): ?object
    {
        $now = $at->format('H:i');
        foreach ($shifts as $s) {
            $inside = $s->cross_day ? ($now >= $s->start || $now < $s->end) : ($now >= $s->start && $now < $s->end);
            if ($inside) {
                return $s;
            }
        }
        $min = fn (string $t): int => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);

        return $shifts->sortBy(fn ($s) => ($min($now) - $min($s->end) + 1440) % 1440)->first();
    }

    /**
     * A QC's shift at a moment (user, 2026-11-25: HR assigns it, the tablet does
     * not ask): the HR employee's roster for the day, else their standing shift.
     * Before noon it is still last night's shift, unless today's has begun. A QC
     * with no HR employee or no shift there works the shift the clock is in.
     *
     * @param  Collection<string, object>  $shifts
     */
    public static function shiftOf(?string $employeeId, CarbonImmutable $at, Collection $shifts): ?object
    {
        if ($employeeId === null) {
            return self::shiftAt($shifts, $at);
        }
        $local = $at->setTimezone(self::TZ);
        $today = self::assignedShift($employeeId, $local->toDateString(), $shifts);
        if ($local->format('H:i') < '12:00') {
            $last = self::assignedShift($employeeId, $local->subDay()->toDateString(), $shifts);
            if ($last?->cross_day && (! $today || $today->cross_day || $local->format('H:i') < $today->start)) {
                return $last;
            }
        }

        return $today ?? self::shiftAt($shifts, $at);
    }

    /** @param  Collection<string, object>  $shifts */
    private static function assignedShift(string $employeeId, string $date, Collection $shifts): ?object
    {
        $id = DB::table('hr_shift_rosters')->where('employee_id', $employeeId)->where('from_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date', '>=', $date))->orderByDesc('created_at')->orderByDesc('id')->value('shift_id')
            ?? DB::table('hr_employees')->where('id', $employeeId)->value('shift_id');

        return $id === null ? null : $shifts->get($id);
    }

    /**
     * The day a tap counts to: its calendar date, except a night shift's
     * after-midnight part, which belongs to the day the shift STARTED (Monday
     * night's 02:00 reports with Monday night, not as Tuesday's first piece).
     */
    public static function productionDate(CarbonImmutable $at, ?object $shift): string
    {
        $local = $at->setTimezone(self::TZ);
        // Its morning overtime too: up to noon (or its end, when that is later) is still last night.
        if ($shift !== null && $shift->cross_day && $local->format('H:i') < max($shift->end, '12:00') && $local->format('H:i') < $shift->start) {
            return $local->subDay()->toDateString();
        }

        return $local->toDateString();
    }

    /**
     * The efficiency denominator for a line on a day: Σ attendance × (shift
     * minutes + overtime) over the shifts recorded; without attendance, the
     * target's manpower × working hours. Null when neither is known — unknown
     * must not read as 0 %.
     */
    public static function manMinutes(string $companyId, string $date, string $section, string $lineId, ?int $targetManpower = null, ?float $targetHours = null): ?int
    {
        $rows = DB::table('qt_line_attendances')->where('company_id', $companyId)->whereDate('att_date', $date)->where('section', $section)->where('line_id', $lineId)->get(['shift_id', 'attendance', 'overtime_minutes']);
        if ($rows->isNotEmpty()) {
            $shifts = self::shifts($companyId);
            $total = 0;
            foreach ($rows as $r) {
                $minutes = $r->shift_id !== '' && $shifts->has($r->shift_id) ? $shifts[$r->shift_id]->minutes : (int) round(($targetHours ?? 8) * 60);
                $total += (int) $r->attendance * ($minutes + (int) $r->overtime_minutes);
            }

            return $total > 0 ? $total : null;
        }

        return $targetManpower && $targetHours ? (int) round($targetManpower * $targetHours * 60) : null;
    }

    /** (pass × SMV) ÷ man-minutes × 100 — null, never 0, when any of them is missing. */
    public static function efficiency(int $pass, ?float $smv, ?int $manMinutes): ?float
    {
        return $smv && $manMinutes ? round($pass * $smv / $manMinutes * 100, 2) : null;
    }

    /**
     * Counts → the numbers every screen shows, in one place so no two screens
     * can disagree.
     *
     * @param  array{RFT?: int, DEFECT?: int, RECTIFY?: int, REJECT?: int}  $counts
     * @return array<string, int|float|null>
     */
    public static function metrics(array $counts, int $tags, ?int $target = null): array
    {
        $rft = (int) ($counts['RFT'] ?? 0);
        $defect = (int) ($counts['DEFECT'] ?? 0);
        $rectify = (int) ($counts['RECTIFY'] ?? 0);
        $reject = (int) ($counts['REJECT'] ?? 0);
        $checked = $rft + $defect;
        $pass = min($rft + $rectify, $checked);

        return [
            'rft' => $rft, 'defect' => $defect, 'rectify' => $rectify, 'reject' => $reject,
            'checked' => $checked, 'pass' => $pass, 'defect_tags' => $tags,
            'dhu' => $checked > 0 ? round($tags / $checked * 100, 2) : null,
            'rft_pct' => $checked > 0 ? round($rft / $checked * 100, 2) : null,
            'defect_pct' => $checked > 0 ? round($defect / $checked * 100, 2) : null,
            'reject_pct' => $checked > 0 ? round($reject / $checked * 100, 2) : null,
            'target' => $target,
            'achievement' => $target ? round($pass / $target * 100, 2) : null,
            'balance' => $target !== null ? $target - $pass : null,
        ];
    }

    /**
     * The panels of each garment type (user, 2026-11-25), offered as a
     * starting list on Panels: garment type => [code, name, how many a piece
     * has — sleeves come in pairs].
     *
     * @return array<string, list<array{0: string, 1: string, 2: int}>>
     */
    public static function standardPanels(): array
    {
        return [
            'T-Shirt' => [['BK', 'Back Part', 1], ['FR', 'Front Part', 1], ['SLV', 'Sleeve', 2]],
            'Jogger / Pant' => [['FL', 'Front Left', 1], ['FRT', 'Front Right', 1], ['BL', 'Back Left', 1], ['BR', 'Back Right', 1], ['PKT', 'Pocket', 1], ['BLT', 'Belt', 1]],
            'Hoody' => [['BK', 'Back', 1], ['FR', 'Front', 1], ['SLV', 'Sleeve', 2], ['PKT', 'Pocket', 1], ['HT', 'Hood Top', 1], ['HI', 'Hood Inner', 1]],
            'Sweatshirt' => [['BK', 'Back', 1], ['FR', 'Front', 1], ['SLV', 'Sleeve', 2]],
            'Polo' => [['BK', 'Back', 1], ['FR', 'Front', 1], ['SLV', 'Sleeve', 2], ['PLK', 'Placket', 1]],
        ];
    }

    /**
     * The usual faults each section checks for, offered as a starting list on
     * Defect Types (code, English, Bangla).
     *
     * @return array<string, list<array{0: string, 1: string, 2: string}>>
     */
    public static function standardDefects(): array
    {
        return [
            // The 100% Cut Panel Inspection Report's codes, in the form's order (Norban Comtex NCL_CTG QA_006).
            'CUTTING' => [
                ['B', 'Bias & Bowing', 'বায়াস ও বোয়িং'],
                ['B1', 'Broken Yarn', 'ভাঙা সুতা'],
                ['B2', 'Brush Problem', 'ব্রাশ সমস্যা'],
                ['C', 'Crease mark', 'ভাঁজের দাগ'],
                ['C1', 'Contamination', 'কন্টামিনেশন'],
                ['C2', 'Color Spot', 'রঙের দাগ'],
                ['C3', 'Cutting Problem', 'কাটিং সমস্যা'],
                ['C4', 'Compact mark', 'কম্প্যাক্ট দাগ'],
                ['D', 'Dirty spot', 'ময়লার দাগ'],
                ['D1', 'Dyeing spot', 'ডাইং দাগ'],
                ['D2', 'Drop needle', 'ড্রপ নিডল'],
                ['F', 'Fabric hole', 'ফেব্রিকে ছিদ্র'],
                ['K', 'Knot', 'গিঁট'],
                ['L', 'Line mark', 'লাইন দাগ'],
                ['L1', 'Lycra out', 'লাইক্রা আউট'],
                ['M', 'Miss Print', 'মিস প্রিন্ট'],
                ['N', 'Needle mark', 'নিডল দাগ'],
                ['O', 'Oil spot', 'তেলের দাগ'],
                ['O1', 'Others', 'অন্যান্য'],
                ['P', 'Pin hole', 'পিন হোল'],
                ['P1', 'Patta', 'পাট্টা'],
                ['S', 'Softener mark', 'সফটনার দাগ'],
                ['S1', 'Slub', 'স্লাব'],
                ['S2', 'Star mark', 'স্টার দাগ'],
                ['S3', 'Sinker mark', 'সিংকার দাগ'],
                ['U', 'Uneven Shade', 'অসমান শেড'],
                ['Y', 'Yarn missing', 'সুতা নেই'],
                ['Y1', 'Yarn contamination', 'সুতা কন্টামিনেশন'],
            ],
            'SEWING' => [
                ['BRK', 'Broken stitch', 'ব্রোকেন স্টিচ'],
                ['SKIP', 'Skip stitch', 'স্কিপ স্টিচ'],
                ['OPEN', 'Open seam', 'ওপেন সিম'],
                ['UNEVEN', 'Uneven stitch', 'অসমান সেলাই'],
                ['PUCK', 'Puckering', 'পাকারিং'],
                ['RAW', 'Raw edge', 'র এজ'],
                ['PLEAT', 'Pleat', 'প্লিট'],
                ['MEAS', 'Wrong measurement', 'ভুল মাপ'],
                ['SPOT', 'Oil / dirty spot', 'তেল / দাগ'],
                ['LABEL', 'Label mistake', 'লেবেল ভুল'],
                ['TWIST', 'Twisting', 'টুইস্টিং'],
                ['MISS', 'Operation missing', 'অপারেশন বাদ'],
            ],
            'QUALITY' => [
                ['MEAS', 'Measurement out of tolerance', 'মাপ টলারেন্সের বাইরে'],
                ['STAIN', 'Stain / spot', 'দাগ'],
                ['THREAD', 'Uncut thread', 'সুতা কাটা হয়নি'],
                ['SHADE', 'Shade variation', 'শেড ভিন্নতা'],
                ['FAB', 'Fabric fault', 'ফেব্রিক ফল্ট'],
                ['STITCH', 'Stitching fault', 'সেলাই ত্রুটি'],
                ['HOLE', 'Hole / damage', 'ছিদ্র / ক্ষতি'],
                ['PRESS', 'Pressing / ironing fault', 'আয়রন ত্রুটি'],
                ['LABEL', 'Wrong / missing label', 'লেবেল ভুল / নেই'],
                ['ACC', 'Accessories missing / wrong', 'এক্সেসরিজ নেই / ভুল'],
            ],
        ];
    }
}
