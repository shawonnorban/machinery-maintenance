<?php

declare(strict_types=1);

use App\Modules\HrPayroll\Support\FactoryCalendarSetup;
use App\Modules\HrPayroll\Support\HolidayCalendar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR & Payroll is the one factory calendar (the user's decision of
 * 2026-11-15). The old Settings → Calendar & Shifts (`factory_calendars`,
 * `shifts`, `shift_breaks`, `factory_holidays`, `production_line_shift_overrides`)
 * and Capacity Planning's own calendar (`planning_calendar_days`,
 * `planning_weekly_holidays`) are folded in and dropped:
 *
 * - every old shift becomes (or is matched by clock time to) a shift on HR &
 *   Payroll → Shift Master, and every column that pointed at an old shift
 *   (Knitting, HR employees and postings) now points at it;
 * - Capacity Planning's line shifts name their HR shift (`hr_shift_id`), and
 *   the old line shift plans move in as line shifts;
 * - the old dated holidays become HR holidays (skipping days HR already has);
 * - a factory calendar's operating mode and weekly off-days become the
 *   factory's `factory.operating_mode` and `general.working_days` settings.
 *
 * A data move: down() does not put the old tables back.
 */
return new class extends Migration
{
    private const ISO = [1 => 'MON', 2 => 'TUE', 3 => 'WED', 4 => 'THU', 5 => 'FRI', 6 => 'SAT', 7 => 'SUN'];

    private const DEFAULT_WORKING_DAYS = ['SAT', 'SUN', 'MON', 'TUE', 'WED', 'THU'];

    public function up(): void
    {
        // A holiday is a holiday without a fiscal year (moved and seeded ones).
        DB::statement('ALTER TABLE hr_holidays ALTER COLUMN fiscal_year_id DROP NOT NULL');

        Schema::table('planning_line_shifts', function (Blueprint $table): void {
            $table->foreignUlid('hr_shift_id')->nullable()->after('planning_line_id')->constrained('hr_shifts')->nullOnDelete();
        });

        $map = Schema::hasTable('shifts') ? $this->moveShifts() : [];
        $this->repointShiftColumns($map);
        $this->moveLineShifts($map);
        $this->moveHolidays();
        $this->moveCalendars();

        foreach (['production_line_shift_overrides', 'shift_breaks', 'factory_holidays', 'factory_calendars', 'shifts', 'planning_calendar_days', 'planning_weekly_holidays'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // A data move — restore the old tables from a backup if ever needed.
    }

    /**
     * Each old shift → an HR shift of its company with the same clock times,
     * else a new HR shift (its code kept when free).
     *
     * @return array<string, string> old shift id => HR shift id
     */
    private function moveShifts(): array
    {
        $map = [];
        foreach (DB::table('shifts')->orderBy('created_at')->get() as $old) {
            $start = substr((string) $old->start_time, 0, 5);
            $end = substr((string) $old->end_time, 0, 5);
            $match = DB::table('hr_shifts')->where('company_id', $old->company_id)->where('start_time', $start)->where('end_time', $end)->value('id');
            if ($match === null) {
                $break = Schema::hasTable('shift_breaks')
                    ? (int) DB::table('shift_breaks')->where('shift_id', $old->id)->where('counts_as_operating_time', false)->get(['start_time', 'end_time'])
                        ->sum(fn ($b) => $this->minutes(substr((string) $b->start_time, 0, 5), substr((string) $b->end_time, 0, 5)))
                    : 0;
                $match = FactoryCalendarSetup::shift($old->company_id, $this->freeCode($old->company_id, (string) $old->code), (string) $old->name, $start, $end, $break, $old->status === 'ACTIVE');
            }
            $map[$old->id] = $match;
        }

        return $map;
    }

    /** Every foreign key on the old `shifts` (other than its own calendar tables) moves to `hr_shifts`. */
    private function repointShiftColumns(array $map): void
    {
        $keys = DB::select(<<<'SQL'
            select t.relname as table_name, a.attname as column_name, c.conname as constraint_name,
                case c.confdeltype when 'c' then 'CASCADE' when 'n' then 'SET NULL' when 'r' then 'RESTRICT' when 'd' then 'SET DEFAULT' else 'NO ACTION' end as delete_rule
            from pg_constraint c
            join pg_class t on t.oid = c.conrelid
            join pg_namespace n on n.oid = t.relnamespace and n.nspname = current_schema()
            join pg_class r on r.oid = c.confrelid and r.relname = 'shifts'
            join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
            where c.contype = 'f' and r.relnamespace = n.oid
            SQL);

        foreach ($keys as $key) {
            if (in_array($key->table_name, ['shift_breaks', 'production_line_shift_overrides'], true)) {
                continue;
            }
            DB::statement("ALTER TABLE \"{$key->table_name}\" DROP CONSTRAINT \"{$key->constraint_name}\"");

            foreach (DB::table($key->table_name)->whereNotNull($key->column_name)->distinct()->pluck($key->column_name) as $oldId) {
                $newId = $map[$oldId] ?? null;
                DB::table($key->table_name)->where($key->column_name, $oldId)->update([$key->column_name => $newId]);
            }

            $onDelete = match ($key->delete_rule) {
                'CASCADE' => 'cascade',
                'SET NULL' => 'set null',
                default => 'restrict',
            };
            Schema::table($key->table_name, function (Blueprint $table) use ($key, $onDelete): void {
                $table->foreign($key->column_name)->references('id')->on('hr_shifts')->onDelete($onDelete);
            });
        }
    }

    /**
     * Capacity Planning's line shifts name their HR shift (matched by clock
     * time, else added from the line shift), and each old line shift plan
     * becomes a line shift of the planning line laid over that floor line.
     */
    private function moveLineShifts(array $map): void
    {
        foreach (DB::table('planning_line_shifts')->whereNull('hr_shift_id')->get() as $row) {
            $start = substr((string) $row->start_time, 0, 5);
            $end = substr((string) $row->end_time, 0, 5);
            $shift = DB::table('hr_shifts')->where('company_id', $row->company_id)->where('start_time', $start)->where('end_time', $end)->value('id')
                ?? FactoryCalendarSetup::shift($row->company_id, $this->freeCode($row->company_id, (string) $row->shift_name), (string) $row->shift_name, $start, $end, (int) $row->break_minutes);
            DB::table('planning_line_shifts')->where('id', $row->id)->update(['hr_shift_id' => $shift]);
        }

        if (! Schema::hasTable('production_line_shift_overrides')) {
            return;
        }
        foreach (DB::table('production_line_shift_overrides')->whereNull('effective_to')->get() as $plan) {
            $shift = $map[$plan->shift_id] ?? null;
            $line = DB::table('planning_lines')->where('company_id', $plan->company_id)->where('floor_line_id', $plan->production_line_id)->value('id');
            if ($shift === null || $line === null || DB::table('planning_line_shifts')->where('planning_line_id', $line)->where('hr_shift_id', $shift)->exists()) {
                continue;
            }
            $hr = DB::table('hr_shifts')->where('id', $shift)->first();
            if (DB::table('planning_line_shifts')->where('planning_line_id', $line)->where('shift_name', $hr->name)->exists()) {
                continue;
            }
            DB::table('planning_line_shifts')->insert([
                'id' => (string) \Illuminate\Support\Str::ulid(), 'company_id' => $plan->company_id, 'planning_line_id' => $line, 'hr_shift_id' => $shift,
                'shift_name' => $hr->name, 'start_time' => $hr->start_time, 'end_time' => $hr->end_time, 'break_minutes' => (int) ($hr->break_min ?? 0),
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** The old dated holidays (non-working days) become HR holidays; days HR already has are skipped. */
    private function moveHolidays(): void
    {
        if (! Schema::hasTable('factory_holidays')) {
            return;
        }
        $days = DB::table('factory_holidays')->where('is_working_day', false)
            ->select('company_id', 'name', 'date')->distinct()->orderBy('company_id')->orderBy('date')->get();

        // Consecutive days of one name are one holiday ("Eid-ul-Fitr", 5 days).
        $runs = [];
        foreach ($days as $day) {
            $date = substr((string) $day->date, 0, 10);
            if (HolidayCalendar::isHoliday($day->company_id, $date)) {
                continue;
            }
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last]['company'] === $day->company_id && $runs[$last]['name'] === $day->name
                && date('Y-m-d', strtotime($runs[$last]['start'].' +'.$runs[$last]['days'].' day')) === $date) {
                $runs[$last]['days']++;

                continue;
            }
            $runs[] = ['company' => $day->company_id, 'name' => $day->name, 'start' => $date, 'days' => 1];
        }

        foreach ($runs as $run) {
            FactoryCalendarSetup::holiday($run['company'], $run['start'], $run['name'], $run['days'], false, str_contains(strtolower($run['name']), 'company') ? 'COMPANY' : 'GOVT');
        }
    }

    /** A factory calendar's mode and weekly off-days become the factory's settings, where they differ from its company's. */
    private function moveCalendars(): void
    {
        if (! Schema::hasTable('factory_calendars')) {
            return;
        }
        $current = DB::table('factory_calendars')->whereNull('effective_to')->orderBy('effective_from')->get()->keyBy('factory_id');

        foreach ($current as $calendar) {
            $own = fn (string $key) => DB::table('settings')->where('company_id', $calendar->company_id)->where('factory_id', $calendar->factory_id)->where('key', $key)->exists();
            $company = fn (string $key) => ($v = DB::table('settings')->where('company_id', $calendar->company_id)->whereNull('factory_id')->where('key', $key)->value('value')) ? (json_decode((string) $v, true)['v'] ?? null) : null;

            if (! $own('factory.operating_mode')) {
                FactoryCalendarSetup::setting($calendar->company_id, $calendar->factory_id, 'factory.operating_mode', 'ENUM', $calendar->operating_mode);
            }

            $off = array_map('intval', json_decode((string) $calendar->weekly_off_days, true) ?: []);
            $working = array_values(array_filter(['SAT', 'SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI'], fn ($d) => ! in_array(array_search($d, self::ISO, true), $off, true)));
            $base = $company('general.working_days') ?? self::DEFAULT_WORKING_DAYS;
            sort($base);
            $sorted = $working;
            sort($sorted);
            if (! $own('general.working_days') && $sorted !== $base) {
                FactoryCalendarSetup::setting($calendar->company_id, $calendar->factory_id, 'general.working_days', 'LIST', $working);
            }
        }
    }

    /** The code when the company has no HR shift with it yet, else code-2, code-3 … (16 characters at most). */
    private function freeCode(string $companyId, string $code): string
    {
        $base = substr(strtoupper(preg_replace('/[^A-Za-z0-9-]+/', '-', trim($code)) ?: 'SHIFT'), 0, 13);
        $candidate = $base;
        for ($n = 2; DB::table('hr_shifts')->where('company_id', $companyId)->where('code', $candidate)->exists(); $n++) {
            $candidate = $base.'-'.$n;
        }

        return $candidate;
    }

    private function minutes(string $start, string $end): int
    {
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        $span = ($eh * 60 + $em) - ($sh * 60 + $sm);

        return $span <= 0 ? $span + 1440 : $span;
    }
};
