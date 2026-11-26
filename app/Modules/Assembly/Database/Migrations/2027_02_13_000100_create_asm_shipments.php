<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Assembly Floor → Shipment (AnnotechERP 20/07), as Brickland keeps it: a
 * shipment entered against a PO and colour from Ready for Shipment — full or
 * partial, never above what is available — planned, confirmed and marked as
 * shipped (taking its quantity out of finished goods stock, oldest packed
 * batch first, with the Ready balance before and after), cancelled while
 * still open, or corrected once shipped (reversed back into stock, and
 * re-shipped at the corrected quantity when one is given).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asm_shipments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('shipment_no', 20); // SHP-20261107-0001
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('po_no', 60)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('style', 80)->nullable();
            $table->string('colour', 60);
            $table->unsignedInteger('qty');
            $table->unsignedInteger('carton_qty')->nullable();
            $table->date('ship_date');
            $table->string('destination', 120)->nullable();
            $table->string('mode', 8)->default('SEA'); // SEA | AIR | ROAD
            $table->string('vehicle_ref', 80)->nullable();
            $table->string('remarks', 300)->nullable();
            $table->string('status', 12)->default('PLANNED'); // PLANNED | CONFIRMED | SHIPPED | CANCELLED | REVERSED
            $table->unsignedInteger('ready_before')->nullable();
            $table->unsignedInteger('remaining_after')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->foreignUlid('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUlid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignUlid('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reverse_reason', 300)->nullable();
            $table->ulid('corrects_id')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'shipment_no']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'order_id', 'colour']);
            $table->index(['company_id', 'ship_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asm_shipments');
    }
};
