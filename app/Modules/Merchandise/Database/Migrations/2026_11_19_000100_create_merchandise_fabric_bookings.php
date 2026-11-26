<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Fabric Booking (AnnotechERP 06/11): fabric booked
 * against an order — bulk, additional, buffer, imported — and, per booking,
 * each dia-wise consumption paired with the garment quantity and colour it
 * applies to, with its process figures (GSM, rate, process loss). The
 * finished fabric quantity of a line is derived when it is saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_fabric_bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('booking_type', 16)->default('BULK');
            $table->string('booking_no', 32);
            $table->unsignedInteger('revision')->default(0);
            $table->date('booking_date');
            $table->ulid('program_by')->nullable();
            $table->ulid('buyer_office_id')->nullable();
            $table->ulid('style_id')->nullable();
            $table->ulid('order_id')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('conv_rate', 14, 4)->default(1);
            $table->string('item_model', 96)->nullable();
            $table->decimal('cutting_wastage', 6, 2)->default(0);
            $table->decimal('yd_wastage', 6, 2)->default(0);
            $table->decimal('aop_wastage', 6, 2)->default(0);
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('approved_at')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'booking_no']);
            $table->index(['company_id', 'booking_type']);
        });

        Schema::create('merch_fabric_booking_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('booking_id')->constrained('merch_fabric_bookings')->cascadeOnDelete();
            $table->string('fabric_kind', 8)->default('KNIT');
            $table->ulid('fabric_type_id');
            $table->ulid('garment_part_id')->nullable();
            $table->decimal('finish_dia', 8, 2)->nullable();
            $table->string('dia_unit', 4)->default('INCH');
            $table->string('dia_type', 4)->default('OPEN');
            $table->decimal('consumption', 12, 4);
            $table->string('cons_unit', 4)->default('KG');
            $table->string('cons_per', 4)->default('DZ');
            $table->boolean('all_orders')->default(true);
            $table->ulid('gmt_style_id');
            $table->boolean('all_colours')->default(false);
            $table->ulid('gmt_colour_id')->nullable();
            $table->boolean('all_sizes')->default(false);
            $table->string('size_group', 16)->nullable();
            $table->boolean('fab_same_as_gmt')->default(false);
            $table->ulid('fab_colour_id')->nullable();
            $table->string('colour_spec', 160)->nullable();
            $table->boolean('contrast')->default(false);
            $table->decimal('cutting_wastage', 6, 2)->default(0);
            $table->decimal('add_qty', 12, 3)->default(0);
            $table->unsignedInteger('disp_order')->default(0);
            $table->decimal('pieces', 14, 2)->default(0);
            $table->decimal('finish_qty', 14, 3)->default(0);
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('rate', 12, 4)->nullable();
            $table->decimal('process_loss', 6, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_fabric_booking_lines');
        Schema::dropIfExists('merch_fabric_bookings');
    }
};
