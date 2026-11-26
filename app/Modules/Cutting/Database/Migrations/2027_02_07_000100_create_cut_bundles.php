<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cutting Floor (AnnotechERP 19/01–19/03): every bundle card cut — its PO,
 * style, colour and size, the cutting number, the serial run it carries, the
 * part and the fabric batch it was cut from — numbered BND-YYYYMMDD-#### and
 * barcoded by that number. A card is printed, reprinted or cancelled; what
 * is cut beyond the order quantity is marked additional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cut_bundles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('bundle_no', 24);
            $table->date('cut_date');
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->string('po_no', 64);
            $table->string('style', 120);
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('colour', 64);
            $table->string('garment', 120)->nullable();
            $table->string('fabric_type', 60)->nullable();
            $table->string('gsm', 16)->nullable();
            $table->string('dia_width', 16)->nullable();
            $table->unsignedInteger('cutting_no');
            $table->string('size', 32);
            $table->unsignedInteger('serial_from');
            $table->unsignedInteger('serial_to');
            $table->unsignedInteger('qty');
            $table->string('parts_name', 60);
            $table->string('batch_no', 40);
            $table->string('cutting_status', 20)->default('REGULAR'); // REGULAR | ADDITIONAL | PARTIAL_ADDITIONAL
            $table->unsignedInteger('additional_qty')->default(0);
            $table->string('status', 10)->default('CREATED'); // CREATED | PRINTED | REPRINTED | CANCELLED
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('printed_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'bundle_no'], 'cut_bundles_no_unique');
            $table->index(['company_id', 'order_id', 'colour']);
            $table->index(['company_id', 'cut_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cut_bundles');
    }
};
