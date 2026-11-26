<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Two shifts in the whole system (user, 2026-11-25): Day 08:00–17:00, break
 * 13:00–14:00, and Night 17:00–08:00, break 01:00–02:00. Every other shift of
 * a company gives its records to one of them — to Night when it starts at
 * 17:00 or later (or before 04:00), else to Day — and goes. A record that
 * would collide there stays where it is and its shift is only made inactive.
 * Finishing's A / B / C / General and the sample room's Evening / General
 * become Day too.
 */
return new class extends Migration
{
    private const SHIFTS = [
        'DAY' => ['name' => 'Day Shift', 'start_time' => '08:00', 'end_time' => '17:00', 'break_start' => '13:00', 'break_min' => 60, 'working_hours' => 8, 'cross_day' => false],
        'NIGHT' => ['name' => 'Night Shift', 'start_time' => '17:00', 'end_time' => '08:00', 'break_start' => '01:00', 'break_min' => 60, 'working_hours' => 14, 'cross_day' => true],
    ];

    /** Brickland's code that becomes ours where the company has no DAY / NIGHT yet. */
    private const OLD = ['DAY' => 'ST-GEN', 'NIGHT' => 'ST-NGT'];

    public function up(): void
    {
        Schema::table('hr_shifts', function (Blueprint $table): void {
            $table->string('break_start', 5)->nullable()->after('end_time');
        });

        $columns = $this->shiftColumns();
        foreach (DB::table('hr_shifts')->distinct()->pluck('company_id') as $company) {
            $keep = [];
            foreach (self::SHIFTS as $code => $values) {
                $values += ['code' => $code, 'grace_min' => 15, 'ot_allowed' => true, 'ot_approval' => true, 'effective_date' => null, 'active' => true, 'updated_at' => now()];
                $id = DB::table('hr_shifts')->where('company_id', $company)->where('code', $code)->value('id')
                    ?? DB::table('hr_shifts')->where('company_id', $company)->where('code', self::OLD[$code])->value('id');
                if ($id === null) {
                    $id = (string) Str::ulid();
                    DB::table('hr_shifts')->insert($values + ['id' => $id, 'company_id' => $company, 'created_at' => now()]);
                } else {
                    DB::table('hr_shifts')->where('id', $id)->update($values);
                }
                $keep[$code] = (string) $id;
            }
            foreach (DB::table('hr_shifts')->where('company_id', $company)->whereNotIn('id', array_values($keep))->get() as $old) {
                $this->retire($old, $keep[$old->start_time >= '17:00' || $old->start_time < '04:00' ? 'NIGHT' : 'DAY'], $columns);
            }
            $this->lineShifts($company, $keep);
        }
        DB::table('hr_payroll_settings')->where('key', 'DEFAULT_SHIFT')->whereNotIn('value', array_keys(self::SHIFTS))->update(['value' => 'DAY']);

        foreach (['fin_inspections', 'fin_production_sheets', 'fin_qc_entries', 'proto_day_sheets'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereNotNull('shift')->whereNotIn('shift', array_keys(self::SHIFTS))->update(['shift' => 'DAY']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('hr_shifts', function (Blueprint $table): void {
            $table->dropColumn('break_start');
        });
    }

    /**
     * Every column that holds a shift: the foreign keys, and Quality Tally's
     * attendance (no key there: '' = no shift).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function shiftColumns(): array
    {
        $keys = DB::select(<<<'SQL'
            select t.relname as table_name, a.attname as column_name
            from pg_constraint c
            join pg_class t on t.oid = c.conrelid
            join pg_namespace n on n.oid = t.relnamespace and n.nspname = current_schema()
            join pg_class r on r.oid = c.confrelid and r.relname = 'hr_shifts' and r.relnamespace = n.oid
            join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
            where c.contype = 'f'
            SQL);
        $out = array_map(fn ($k) => [$k->table_name, $k->column_name], $keys);
        if (Schema::hasTable('qt_line_attendances')) {
            $out[] = ['qt_line_attendances', 'shift_id'];
        }

        return $out;
    }

    /** @param  list<array{0: string, 1: string}>  $columns */
    private function retire(object $old, string $to, array $columns): void
    {
        $stuck = false;
        foreach ($columns as [$table, $column]) {
            // An overtime window is not a shift.
            if ($table === 'planning_line_shifts' && strcasecmp($old->name, 'Overtime') === 0) {
                DB::table($table)->where($column, $old->id)->delete();

                continue;
            }
            foreach (DB::table($table)->where($column, $old->id)->pluck('id') as $id) {
                try {
                    DB::transaction(fn () => DB::table($table)->where('id', $id)->update([$column => $to]));
                } catch (UniqueConstraintViolationException) {
                    $stuck = true;
                }
            }
        }
        if ($stuck) {
            DB::table('hr_shifts')->where('id', $old->id)->update(['active' => false, 'updated_at' => now()]);
        } else {
            DB::table('hr_shifts')->where('id', $old->id)->delete();
        }
    }

    /**
     * Capacity Planning: a planning line keeps one row for Day and one for
     * Night, with the new times.
     *
     * @param  array<string, string>  $keep
     */
    private function lineShifts(string $company, array $keep): void
    {
        foreach ($keep as $code => $shift) {
            $s = self::SHIFTS[$code];
            $lines = DB::table('planning_line_shifts')->where('company_id', $company)->where('hr_shift_id', $shift)->orderBy('created_at')->orderBy('id')->get()->groupBy('planning_line_id');
            foreach ($lines as $rows) {
                DB::table('planning_line_shifts')->whereIn('id', $rows->skip(1)->pluck('id')->all())->delete();
                DB::table('planning_line_shifts')->where('id', $rows->first()->id)->update([
                    'shift_name' => $s['name'], 'start_time' => $s['start_time'], 'end_time' => $s['end_time'], 'break_minutes' => $s['break_min'], 'updated_at' => now(),
                ]);
            }
        }
    }
};
