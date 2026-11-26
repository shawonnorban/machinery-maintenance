<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sales & Shipment → General → Shipment Planning (AnnotechERP 21/04): the
 * shipment itself, as Brickland keeps it — one shipment of a confirmed
 * order (SHP-2026-000001, the prefix and width from 21/01), planned with its
 * shipment and ex-factory dates, mode, destination, port of loading and
 * incoterm, and the order lines it carries with cartons and rate. Its stage
 * moves on as the shipment screens (21/05) book, document, ready, confirm
 * and close it: PLANNING → BOOKED → DOCUMENTATION → READY → CONFIRMED →
 * CLOSED, or CANCELLED.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ss_shipments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('shipment_no', 24);
            $table->foreignUlid('order_id')->constrained('ss_sales_orders')->restrictOnDelete();
            $table->date('plan_date');
            $table->date('shipment_date');
            $table->date('ex_factory_date')->nullable();
            $table->string('shipment_mode', 10); // Sea | Air | Road | Sea-Air | Courier
            $table->foreignUlid('destination_country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->string('destination_port', 80)->nullable();
            $table->string('port_of_loading', 80)->nullable();
            $table->string('incoterm', 4)->nullable();
            $table->text('remarks')->nullable();
            $table->string('stage', 14)->default('PLANNING'); // PLANNING | BOOKED | DOCUMENTATION | READY | CONFIRMED | CLOSED | CANCELLED
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUlid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'shipment_no']);
        });

        Schema::create('ss_shipment_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('shipment_id')->constrained('ss_shipments')->cascadeOnDelete();
            $table->foreignUlid('size_id')->constrained('ss_sales_order_sizes')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->unsignedInteger('carton_qty')->default(0);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('shipped_qty', 16, 4)->default(0); // set when the shipment is confirmed (21/05)
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ss_shipment_lines');
        Schema::dropIfExists('ss_shipments');
    }
};
