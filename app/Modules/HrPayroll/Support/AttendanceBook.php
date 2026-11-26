<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Support;

use App\Shared\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Daily attendance worked out from the terminals' punches (AnnotechERP 24/04).
 *
 * For each employee and day:
 *
 * - **Shift:** the newest Shift Roster assignment covering the day, else the
 *   company's general shift. Its weekly off days come with it (Friday when
 *   none is set).
 * - **Punches:** the employee's matched punches from four hours before the
 *   shift starts to six hours after it ends (never into the next day's
 *   window). The first is IN; the last is OUT when it is at least an hour
 *   after IN.
 * - **Status:** a holiday on the factory calendar or a weekly off day stays
 *   Holiday / Weekly Off (with the hours worked as OT when the employee came
 *   in); no punch on a working day is Absent; IN without OUT is Missing Punch
 *   (today, while the shift may still be running, it is Present); IN and OUT
 *   is Present.
 * - **Figures:** worked = OUT − IN − the shift's break; late = minutes after
 *   the shift's start once past the grace minutes; early out = minutes before
 *   the shift's regular end (its start + its working hours + its break: a
 *   night of 8 regular hours from 17:00 ends at 02:00, the rest of it is
 *   overtime — user, 2026-11-25); OT as OtBook counts it (24/05) — for an
 *   employee who earns General or Worker OT, on a shift that allows it, from
 *   the OT start after the regular end (all of an off day's hours); only inside the
 *   approved OT window where an OT request for the day was approved.
 * - **Leave (24/05):** an approved daily leave makes its days Leave (an off
 *   day or holiday inside it only when the leave type includes them); an
 *   approved early departure excuses that day's early out.
 *
 * Times are Dhaka's. A corrected day (Attendance Correction, an approved
 * missing-punch correction) is kept as it is and not worked out again.
 */
final class AttendanceBook
{
    public const STATUSES = ['PRESENT', 'ABSENT', 'LEAVE', 'MISSING_PUNCH', 'HOLIDAY', 'WEEKLY_OFF'];

    public const DAYS = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

    private const TZ = 'Asia/Dhaka';

    private const BEFORE_MIN = 240;

    private const AFTER_MIN = 360;

    private const MIN_SPAN_MIN = 60;

    public function __construct(private readonly string $companyId) {}

    /**
     * Work out the days from $from to $to (never past today) for the
     * company's employees, or the ones given. Answers how many days were written.
     *
     * @param  list<string>|null  $employeeIds
     */
    public function process(string $from, string $to, ?array $employeeIds = null): int
    {
        $today = CarbonImmutable::now(self::TZ)->startOfDay();
        $start = CarbonImmutable::parse($from, self::TZ)->startOfDay();
        $end = CarbonImmutable::parse($to, self::TZ)->startOfDay();
        if ($end->greaterThan($today)) {
            $end = $today;
        }
        if ($start->greaterThan($end)) {
            return 0;
        }
        $employees = DB::table('hr_employees')->where('company_id', $this->companyId)->whereIn('status', ['ACTIVE', 'SUSPENDED', 'INACTIVE', 'SEPARATED'])
            ->when($employeeIds !== null, fn ($q) => $q->whereIn('id', $employeeIds))->get();
        if ($employees->isEmpty()) {
            return 0;
        }
        $ids = $employees->pluck('id')->all();
        $shifts = $this->shifts();
        $default = $this->defaultShift($shifts);
        $rosters = DB::table('hr_shift_rosters')->where('company_id', $this->companyId)->whereIn('employee_id', $ids)
            ->where('from_date', '<=', $end->toDateString())->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date', '>=', $start->toDateString()))
            ->orderByDesc('created_at')->orderByDesc('id')->get()->groupBy('employee_id');
        // HR & Payroll's own holidays — the one factory calendar.
        $holidays = HolidayCalendar::dates($this->companyId, $start->toDateString(), $end->toDateString());
        $punches = DB::table('dev_punches')->where('company_id', $this->companyId)->whereIn('employee_id', $ids)
            ->whereBetween('punched_at', [$start->subDay()->utc(), $end->addDays(2)->utc()])->orderBy('punched_at')->get(['employee_id', 'punched_at'])
            ->groupBy('employee_id')->map(fn ($g) => $g->map(fn ($p) => CarbonImmutable::parse($p->punched_at)->timezone(self::TZ))->values());
        $corrected = DB::table('hr_attendance_days')->where('company_id', $this->companyId)->whereIn('employee_id', $ids)->where('corrected', true)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get(['employee_id', 'date'])
            ->map(fn ($d) => $d->employee_id.'|'.substr((string) $d->date, 0, 10))->flip();
        $ot = new OtBook($this->companyId);
        $eligible = $ot->eligibility($employees);
        $windows = $ot->windows($start->toDateString(), $end->toDateString(), $ids);
        [$leaves, $early] = $this->leaves($start->toDateString(), $end->toDateString(), $ids);
        $now = CarbonImmutable::now(self::TZ);
        $rows = [];
        for ($d = $start; $d->lessThanOrEqualTo($end); $d = $d->addDay()) {
            $date = $d->toDateString();
            foreach ($employees as $e) {
                if (isset($corrected["{$e->id}|{$date}"]) || ! $this->employed($e, $date)) {
                    continue;
                }
                [$shift, $off] = $this->shiftFor($rosters[$e->id] ?? collect(), $date, $shifts, $default);
                if (! $shift) {
                    continue;
                }
                $holiday = isset($holidays[$date]);
                $key = "{$e->id}|{$date}";
                $row = $this->day($d, $shift, $off, $holiday, $punches[$e->id] ?? collect(), $now, false, $ot, $eligible[$e->id]['type'] ?? null, $windows[$key] ?? null, $leaves[$key] ?? null, isset($early[$key]));
                if ($row !== null) {
                    $rows[] = $row + ['employee_id' => $e->id, 'date' => $date];
                }
            }
        }
        $this->write($rows);

        return count($rows);
    }

    /**
     * The figures for a day from a given IN and OUT (a correction): worked,
     * late, early out, OT and the status, by the day's shift.
     *
     * @return array<string, mixed>
     */
    public function fromTimes(string $employeeId, string $date, ?string $in, ?string $out): array
    {
        $e = DB::table('hr_employees')->where('id', $employeeId)->first();
        $shifts = $this->shifts();
        $rosters = DB::table('hr_shift_rosters')->where('employee_id', $employeeId)->where('from_date', '<=', $date)->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date', '>=', $date))
            ->orderByDesc('created_at')->orderByDesc('id')->get();
        [$shift, $off] = $this->shiftFor($rosters, $date, $shifts, $this->defaultShift($shifts));
        $d = CarbonImmutable::parse($date, self::TZ)->startOfDay();
        [$s, $end] = $this->bounds($d, $shift);
        $at = function (?string $hm) use ($d, $s): ?CarbonImmutable {
            if ($hm === null || $hm === '') {
                return null;
            }
            $t = CarbonImmutable::parse($d->toDateString().' '.$hm, self::TZ);

            // A time well before the shift starts belongs to the next morning (a night shift's OUT).
            return $t->lessThan($s->subHours(6)) ? $t->addDay() : $t;
        };
        $holiday = HolidayCalendar::isHoliday($this->companyId, $date);
        $punches = collect(array_values(array_filter([$at($in), $at($out)])));
        $ot = new OtBook($this->companyId);
        $key = "{$employeeId}|{$date}";
        [$leaves, $early] = $this->leaves($date, $date, [$employeeId]);
        $type = $ot->eligibility(collect([$e]))[$employeeId]['type'] ?? null;
        $row = $this->day($d, $shift, $off, $holiday, $punches, CarbonImmutable::now(self::TZ)->addYear(), true, $ot, $type, $ot->windows($date, $date, [$employeeId])[$key] ?? null, $leaves[$key] ?? null, isset($early[$key]));

        return $row ?? [];
    }

    /** The shifts by id, with their grace, break and OT rules. */
    public function shifts(): Collection
    {
        return DB::table('hr_shifts')->where('company_id', $this->companyId)->where('active', true)->orderBy('start_time')->get()->keyBy('id');
    }

    /** "General 08:00–17:00" */
    public static function label(?object $shift): ?string
    {
        return $shift ? $shift->name.' '.Clock::range($shift->start_time, $shift->end_time) : null;
    }

    /** The shift's regular end, as a clock time: its start + working hours + break, never past its end. */
    public static function regularEnd(?object $shift): ?string
    {
        if (! $shift || ! $shift->start_time || ! $shift->end_time) {
            return null;
        }
        $min = fn (string $t): int => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
        $start = $min($shift->start_time);
        $span = ($min($shift->end_time) - $start + 1440) % 1440 ?: 1440;
        $regular = $shift->working_hours === null || (float) $shift->working_hours <= 0 ? $span : min($span, (int) round((float) $shift->working_hours * 60) + (int) ($shift->break_min ?? 0));
        $at = ($start + $regular) % 1440;

        return sprintf('%02d:%02d', intdiv($at, 60), $at % 60);
    }

    /* ── One day ───────────────────────────────────────────────────── */

    /**
     * @param  Collection<int, CarbonImmutable>  $punches  the employee's punches around the day, in order
     * @return array<string, mixed>|null
     */
    private function day(CarbonImmutable $d, object $shift, array $off, bool $holiday, Collection $punches, CarbonImmutable $now, bool $given, OtBook $book, ?string $otType, ?array $window, ?object $leave, bool $excused): ?array
    {
        [$s, $e] = $this->bounds($d, $shift);
        $regular = CarbonImmutable::parse($d->toDateString().' '.self::regularEnd($shift), self::TZ);
        $regular = $regular->lessThanOrEqualTo($s) ? $regular->addDay() : $regular;
        $grace = (int) ($shift->grace_min ?? $this->setting('DEFAULT_GRACE_MINUTES', 15));
        $offDay = $holiday || in_array(self::DAYS[$d->dayOfWeek], $off, true);
        $from = $s->subMinutes(self::BEFORE_MIN);
        $to = $e->addMinutes(self::AFTER_MIN)->min($s->addDay()->subMinutes(self::BEFORE_MIN));
        $in = $punches->filter(fn ($p) => $given || ($p->greaterThanOrEqualTo($from) && $p->lessThanOrEqualTo($to)))->values();
        $first = $in->first();
        $last = $in->count() > 1 ? $in->last() : null;
        if ($last && ! $given && $first->diffInMinutes($last) < self::MIN_SPAN_MIN) {
            $last = null;
        }
        $base = ['shift_id' => $shift->id, 'in_at' => $first?->utc(), 'out_at' => $last?->utc(), 'worked_minutes' => null, 'late_minutes' => 0, 'early_out_minutes' => 0, 'ot_minutes' => null, 'ot_type' => null, 'leave_type_id' => null, 'is_late' => false, 'is_early_out' => false];
        // An approved leave: its days are Leave — an off day or holiday only when the leave type takes them in.
        if ($leave && (! $offDay || ($holiday ? $leave->holiday_included : $leave->offday_included))) {
            return ['status' => 'LEAVE', 'leave_type_id' => $leave->leave_type_id] + $base;
        }
        if (! $first) {
            if ($offDay) {
                return $base + ['status' => $holiday ? 'HOLIDAY' : 'WEEKLY_OFF'];
            }
            // Today, before the shift has properly started, nobody is absent yet.
            if ($now->lessThan($s->addMinutes($grace))) {
                return null;
            }

            return $base + ['status' => 'ABSENT'];
        }
        $worked = $last ? max(0, (int) $first->diffInMinutes($last) - (int) ($shift->break_min ?? 0)) : null;
        $ot = null;
        if ($last) {
            $ot = $otType && $shift->ot_allowed ? $book->count($this->otRaw($first, $last, $regular, $offDay, (int) $worked, $window, $book), $otType) : 0;
        }
        $otKind = $ot ? $otType : null;
        if ($offDay) {
            return ['worked_minutes' => $worked, 'ot_minutes' => $ot, 'ot_type' => $otKind, 'status' => $holiday ? 'HOLIDAY' : 'WEEKLY_OFF'] + $base;
        }
        $lateBy = (int) $s->diffInMinutes($first, false);
        $late = $lateBy > $grace ? $lateBy : 0;
        $early = $last && ! $excused ? max(0, (int) $last->diffInMinutes($regular, false)) : 0;
        // IN without OUT: a missing punch once the shift's window has closed; today it is still running.
        $status = $last ? 'PRESENT' : ($now->greaterThan($to) ? 'MISSING_PUNCH' : 'PRESENT');

        return ['worked_minutes' => $worked, 'late_minutes' => $late, 'early_out_minutes' => $early, 'ot_minutes' => $ot, 'ot_type' => $otKind, 'is_late' => $late > 0, 'is_early_out' => $early > 0, 'status' => $status] + $base;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the shift's start and end on a day */
    private function bounds(CarbonImmutable $d, object $shift): array
    {
        $s = CarbonImmutable::parse($d->toDateString().' '.$shift->start_time, self::TZ);
        $e = CarbonImmutable::parse($d->toDateString().' '.$shift->end_time, self::TZ);
        if ($shift->cross_day || $e->lessThanOrEqualTo($s)) {
            $e = $e->addDay();
        }

        return [$s, $e];
    }

    /** @return array{0: ?object, 1: list<string>} the day's shift and weekly off days */
    private function shiftFor(Collection $rosters, string $date, Collection $shifts, ?object $default): array
    {
        $r = $rosters->first(fn ($r) => substr((string) $r->from_date, 0, 10) <= $date && ($r->to_date === null || substr((string) $r->to_date, 0, 10) >= $date));
        $off = $r ? (json_decode((string) $r->weekly_off, true) ?: []) : ['FRI'];

        return [$r ? ($shifts[$r->shift_id] ?? $default) : $default, $off];
    }

    private function defaultShift(Collection $shifts): ?object
    {
        $code = $this->setting('DEFAULT_SHIFT', HrDefaults::DEFAULT_SHIFT);

        return $shifts->firstWhere('code', $code) ?? $shifts->first();
    }

    private function employed(object $e, string $date): bool
    {
        if ($e->joining_date && substr((string) $e->joining_date, 0, 10) > $date) {
            return false;
        }
        if ($e->separation_date && substr((string) $e->separation_date, 0, 10) < $date) {
            return false;
        }

        return $e->status !== 'SEPARATED' || $e->separation_date !== null;
    }

    /**
     * The minutes of a day's OT before the limits round them: from the OT
     * start (the shift's regular end + the limits' start-after; IN on an off day, where all
     * the hours worked count) to OUT — only inside the approved window when
     * an OT request for the day was approved.
     */
    private function otRaw(CarbonImmutable $first, CarbonImmutable $last, CarbonImmutable $end, bool $offDay, int $worked, ?array $window, OtBook $book): int
    {
        $start = $end->addMinutes((int) $book->limits()->start_after_min);
        if (! $window) {
            return $offDay ? $worked : max(0, (int) $start->diffInMinutes($last, false));
        }
        $from = CarbonImmutable::parse($window[0], self::TZ)->max($offDay ? $first : $start);
        $to = CarbonImmutable::parse($window[1], self::TZ)->min($last);

        return max(0, (int) $from->diffInMinutes($to, false));
    }

    /**
     * Approved leave over a range: daily leave by "employee|date" (with the
     * leave type's off-day and holiday rules), and the early departures.
     *
     * @param  list<string>  $ids
     * @return array{0: array<string, object>, 1: array<string, true>}
     */
    private function leaves(string $from, string $to, array $ids): array
    {
        $rows = DB::table('hr_leave_applications')->join('hr_leave_types', 'hr_leave_types.id', '=', 'hr_leave_applications.leave_type_id')
            ->where('hr_leave_applications.company_id', $this->companyId)->where('hr_leave_applications.status', 'APPROVED')->whereIn('hr_leave_applications.employee_id', $ids)
            ->where('hr_leave_applications.from_date', '<=', $to)->where('hr_leave_applications.to_date', '>=', $from)
            ->get(['hr_leave_applications.employee_id', 'hr_leave_applications.mode', 'hr_leave_applications.from_date', 'hr_leave_applications.to_date', 'hr_leave_applications.leave_type_id', 'hr_leave_types.offday_included', 'hr_leave_types.holiday_included']);
        $daily = [];
        $early = [];
        foreach ($rows as $r) {
            $d = CarbonImmutable::parse(max(substr((string) $r->from_date, 0, 10), $from));
            $last = min(substr((string) $r->to_date, 0, 10), $to);
            for (; $d->toDateString() <= $last; $d = $d->addDay()) {
                $key = "{$r->employee_id}|{$d->toDateString()}";
                if ($r->mode === 'EARLY_DEPARTURE') {
                    $early[$key] = true;
                } else {
                    $daily[$key] = (object) ['leave_type_id' => $r->leave_type_id, 'offday_included' => (bool) $r->offday_included, 'holiday_included' => (bool) $r->holiday_included];
                }
            }
        }

        return [$daily, $early];
    }

    private function setting(string $key, mixed $default): mixed
    {
        return DB::table('hr_payroll_settings')->where('company_id', $this->companyId)->where('key', $key)->value('value') ?? $default;
    }

    /** @param list<array<string, mixed>> $rows */
    private function write(array $rows): void
    {
        $now = now();
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('hr_attendance_days')->upsert(array_map(fn ($r) => $r + ['id' => strtolower((string) Str::ulid()), 'company_id' => $this->companyId, 'corrected' => false, 'processed_at' => $now, 'created_at' => $now, 'updated_at' => $now], $chunk),
                ['employee_id', 'date'], ['shift_id', 'in_at', 'out_at', 'worked_minutes', 'late_minutes', 'early_out_minutes', 'ot_minutes', 'ot_type', 'leave_type_id', 'status', 'is_late', 'is_early_out', 'processed_at', 'updated_at']);
        }
    }
}
