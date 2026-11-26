<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The night shift is 20:00–05:00 (user, 2026-11-25, correcting 17:00–08:00), break 01:00–02:00: 8 regular hours. */
return new class extends Migration
{
    public function up(): void
    {
        $this->set('17:00', '08:00', '20:00', '05:00');
    }

    public function down(): void
    {
        $this->set('20:00', '05:00', '17:00', '08:00');
    }

    private function set(string $fromStart, string $fromEnd, string $start, string $end): void
    {
        foreach (DB::table('hr_shifts')->where('code', 'NIGHT')->where('start_time', $fromStart)->where('end_time', $fromEnd)->pluck('id') as $id) {
            DB::table('hr_shifts')->where('id', $id)->update(['start_time' => $start, 'end_time' => $end, 'break_start' => '01:00', 'break_min' => 60, 'working_hours' => 8, 'cross_day' => true, 'updated_at' => now()]);
            DB::table('planning_line_shifts')->where('hr_shift_id', $id)->update(['start_time' => $start, 'end_time' => $end, 'updated_at' => now()]);
        }
    }
};
