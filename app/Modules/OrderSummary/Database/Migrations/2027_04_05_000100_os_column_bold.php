<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order Summary (AnnotechERP 32): a column's data may show bold — a Column
 * Setup switch (user, 2026-11-21). JOB No, Quantity (Ticket), Quantity
 * (PCS), FOB and FOB Value start bold.
 */
return new class extends Migration
{
    private const BOLD = ['job_no', 'quantity_ticket', 'quantity_pcs', 'fob', 'fob_value'];

    public function up(): void
    {
        Schema::table('os_columns', function (Blueprint $table): void {
            $table->boolean('bold')->default(false)->after('text_tone');
        });
        DB::table('os_columns')->whereIn('key', self::BOLD)->update(['bold' => true]);
    }

    public function down(): void
    {
        Schema::table('os_columns', function (Blueprint $table): void {
            $table->dropColumn('bold');
        });
    }
};
