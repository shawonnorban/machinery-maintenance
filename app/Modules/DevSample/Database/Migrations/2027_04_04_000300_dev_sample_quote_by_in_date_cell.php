<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dev Sample → Last Price Quote Date shows who entered it in its own cell,
 * as Norban's list does (user 2026-11-21): the date, and under it the name.
 * The separate Quote Entered By column goes (the name stays in each sample's
 * values, set on save as before); the date column turns upright and wide
 * enough for both lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ds_columns')->where('key', 'last_price_quote_by')->delete();
        DB::table('ds_columns')->where('key', 'last_price_quote_date')->update(['vertical' => false, 'width' => 110, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The column comes back from Column Setup's catalog only on a new company; nothing to undo here.
    }
};
