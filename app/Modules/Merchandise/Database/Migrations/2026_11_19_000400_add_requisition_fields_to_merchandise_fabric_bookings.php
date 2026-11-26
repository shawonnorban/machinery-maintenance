<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fabric Booking → Additional Fabric Booking Entry (AnnotechERP 06/11): a
 * requisition keyed by its header alone — the work style, reference, ship
 * date, remarks and whether it is still active — and its fabric lines added
 * on the Booking Items panel, each a plain fabric, colour, GSM, dia, quantity,
 * unit, rate and process loss rather than a consumption over the order. Such
 * a line has no garment item or consumption of its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_fabric_bookings', function (Blueprint $table): void {
            $table->string('work_style_no', 64)->nullable()->after('item_model');
            $table->string('ref_no', 64)->nullable()->after('work_style_no');
            $table->date('ship_date')->nullable()->after('sp_no');
            $table->text('remarks')->nullable()->after('ship_date');
            $table->boolean('active')->default(true)->after('remarks');
        });

        Schema::table('merch_fabric_booking_lines', function (Blueprint $table): void {
            $table->ulid('gmt_style_id')->nullable()->change();
            $table->decimal('consumption', 12, 4)->nullable()->change();
            $table->string('uom', 16)->nullable()->after('finish_qty');
        });
    }

    public function down(): void
    {
        Schema::table('merch_fabric_booking_lines', function (Blueprint $table): void {
            $table->dropColumn('uom');
        });
        Schema::table('merch_fabric_bookings', function (Blueprint $table): void {
            $table->dropColumn(['work_style_no', 'ref_no', 'ship_date', 'remarks', 'active']);
        });
    }
};
