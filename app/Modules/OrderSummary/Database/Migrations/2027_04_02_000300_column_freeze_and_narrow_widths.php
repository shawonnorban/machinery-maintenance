<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order Summary (AnnotechERP 32): frozen columns, set in Column Setup — the
 * sheet keeps every column up to the marked one on screen while scrolling
 * right (Excel's Freeze Panes). Vertical columns now follow their own width
 * too, so the starting ones get a rotated-text width instead of the old wide
 * default (only where nobody changed it).
 */
return new class extends Migration
{
    /** Vertical starting columns → the width they were seeded with. */
    private const OLD_WIDTHS = ['order_type' => 140, 'tp_rcvd_date' => 130, 'last_update' => 160, 'po_wise_ldd_etd' => 130, 'days_left_in_hand' => 100, 'price_status' => 140];

    public function up(): void
    {
        Schema::table('os_columns', function (Blueprint $table): void {
            $table->boolean('frozen')->default(false)->after('vertical');
        });
        foreach (self::OLD_WIDTHS as $key => $width) {
            DB::table('os_columns')->where('key', $key)->where('vertical', true)->where('width', $width)->update(['width' => 34]);
        }
        $withFreeze = DB::table('os_columns')->where('frozen', true)->pluck('company_id')->all();
        DB::table('os_columns')->where('key', 'style_number_name')->whereNotIn('company_id', $withFreeze)->update(['frozen' => true]);
    }

    public function down(): void
    {
        Schema::table('os_columns', function (Blueprint $table): void {
            $table->dropColumn('frozen');
        });
    }
};
