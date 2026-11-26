<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The user's decisions of 2026-11-25:
 * - a bundle is given as cutting input without naming a table; the table that
 *   first checks it takes it (one given but never checked is any table's);
 * - the night shift is 8 regular hours, the rest of it overtime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qt_cut_bundles', function (Blueprint $table): void {
            $table->char('line_id', 26)->nullable()->change();
        });
        DB::table('qt_cut_bundles')->whereNotIn('id', DB::table('qt_entries')->whereNotNull('cut_bundle_id')->select('cut_bundle_id'))->update(['line_id' => null]);
        DB::table('hr_shifts')->where('code', 'NIGHT')->where('start_time', '17:00')->where('end_time', '08:00')->update(['working_hours' => 8, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('hr_shifts')->where('code', 'NIGHT')->where('start_time', '17:00')->where('end_time', '08:00')->update(['working_hours' => 14]);
    }
};
