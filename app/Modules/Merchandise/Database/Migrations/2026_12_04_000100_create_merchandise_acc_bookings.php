<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Accessories & Trims (AnnotechERP 06/08): the accessory
 * bookings raised against an order (a PO to a supplier, its trims below), the
 * sourcing line chosen for each trim of an order (supplier, buyer-nominated
 * or not), and the dyeing programmes for tape, thread and other dyeable trims.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_acc_bookings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('booking_no', 20);
            $table->ulid('order_id');
            $table->string('po_no', 60)->nullable();
            $table->string('subject', 200)->nullable();
            $table->ulid('supplier_id')->nullable();
            $table->string('req_type', 8)->default('BULK');
            $table->date('po_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'booking_no']);
            $table->index(['company_id', 'order_id']);
        });

        Schema::create('merch_acc_booking_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('booking_id')->constrained('merch_acc_bookings')->cascadeOnDelete();
            $table->ulid('item_id')->nullable();
            $table->string('description', 200);
            $table->string('colour', 80)->nullable();
            $table->string('size', 40)->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16)->nullable();
            $table->decimal('rate', 14, 4)->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'booking_id']);
        });

        Schema::create('merch_trim_sourcings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('order_id');
            $table->ulid('item_id')->nullable();
            $table->string('description', 200);
            $table->ulid('supplier_id')->nullable();
            $table->boolean('nominated')->default(false);
            $table->string('status', 12)->default('DRAFT');
            $table->text('remarks')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'order_id']);
        });

        Schema::create('merch_trim_dyeing_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 20);
            $table->string('prod_type', 12)->default('BULK');
            $table->ulid('buyer_id')->nullable();
            $table->ulid('order_id')->nullable();
            $table->date('program_date');
            $table->string('status', 12)->default('DRAFT');
            $table->text('remarks')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
        });

        Schema::create('merch_trim_dyeing_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('merch_trim_dyeing_programs')->cascadeOnDelete();
            $table->ulid('item_id')->nullable();
            $table->string('description', 200);
            $table->ulid('colour_id')->nullable();
            $table->string('colour_name', 80);
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_trim_dyeing_items');
        Schema::dropIfExists('merch_trim_dyeing_programs');
        Schema::dropIfExists('merch_trim_sourcings');
        Schema::dropIfExists('merch_acc_booking_lines');
        Schema::dropIfExists('merch_acc_bookings');
    }
};
