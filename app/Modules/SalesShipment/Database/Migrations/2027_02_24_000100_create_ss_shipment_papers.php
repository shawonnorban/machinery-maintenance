<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sales & Shipment → Shipment (AnnotechERP 21/05): the papers a planned
 * shipment (21/04) gathers on its way out, as Brickland keeps them.
 *
 * - `ss_bookings` (BKG-2026-000001): the forwarder's booking — line, vessel,
 *   voyage, ETD / ETA, ports, container type and count; requested,
 *   confirmed, completed or cancelled.
 * - `ss_containers`: each container's number, seal, weights, CBM, cartons,
 *   vessel and dates (blanks taken from its booking).
 * - `ss_doc_handovers` (DH-2026-000001): commercial documents handed from
 *   Commercial to Shipping — pending, handed over, received or returned.
 * - `ss_invoices` + lines (INV-2026-000001): the shipment invoice, drafted,
 *   finalized or cancelled; never more than the shipment carries.
 * - `ss_packing_lists` + lines (PL-2026-000001): carton runs per shipment
 *   line, drafted, finalized or cancelled.
 * - `ss_shipments` gains who confirmed (shipped) and who closed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $base = function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
        };
        $shipment = fn (Blueprint $table) => $table->foreignUlid('shipment_id')->constrained('ss_shipments')->restrictOnDelete();

        Schema::table('ss_shipments', function (Blueprint $table): void {
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignUlid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('ss_bookings', function (Blueprint $table) use ($base, $shipment): void {
            $base($table);
            $table->string('booking_no', 24);
            $shipment($table);
            $table->date('booking_date');
            $table->string('forwarder', 160);
            $table->string('shipping_line', 160)->nullable();
            $table->string('vessel_name', 120)->nullable();
            $table->string('voyage_no', 40)->nullable();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->string('port_of_loading', 80)->nullable();
            $table->string('port_of_discharge', 80)->nullable();
            $table->string('final_destination', 120)->nullable();
            $table->string('container_type', 12)->nullable();
            $table->unsignedInteger('container_qty')->default(0);
            $table->string('status', 10)->default('REQUESTED'); // REQUESTED | CONFIRMED | COMPLETED | CANCELLED
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'booking_no']);
        });

        Schema::create('ss_containers', function (Blueprint $table) use ($base, $shipment): void {
            $base($table);
            $shipment($table);
            $table->foreignUlid('booking_id')->nullable()->constrained('ss_bookings')->nullOnDelete();
            $table->string('container_no', 11);
            $table->string('container_type', 12)->nullable();
            $table->string('seal_no', 40)->nullable();
            $table->decimal('gross_weight', 14, 3)->default(0);
            $table->decimal('net_weight', 14, 3)->default(0);
            $table->decimal('cbm', 12, 4)->default(0);
            $table->unsignedInteger('carton_qty')->default(0);
            $table->string('vessel_name', 120)->nullable();
            $table->string('voyage_no', 40)->nullable();
            $table->date('loading_date')->nullable();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['shipment_id', 'container_no']);
        });

        Schema::create('ss_invoices', function (Blueprint $table) use ($base, $shipment): void {
            $base($table);
            $table->string('invoice_no', 24);
            $shipment($table);
            $table->date('invoice_date');
            $table->foreignUlid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->string('commercial_invoice_ref', 40)->nullable();
            $table->text('consignee');
            $table->text('notify_party')->nullable();
            $table->foreignUlid('country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->string('port', 80)->nullable();
            $table->string('shipment_mode', 10);
            $table->string('incoterm', 4)->nullable();
            $table->string('payment_terms', 120)->nullable();
            $table->string('lc_contract_no', 60)->nullable();
            $table->text('remarks')->nullable();
            $table->string('state', 10)->default('DRAFT'); // DRAFT | FINAL | CANCELLED
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'invoice_no']);
        });

        Schema::create('ss_invoice_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('invoice_id')->constrained('ss_invoices')->cascadeOnDelete();
            $table->foreignUlid('shipment_line_id')->constrained('ss_shipment_lines')->restrictOnDelete();
            $table->string('description', 300)->nullable();
            $table->decimal('qty', 16, 4);
            $table->decimal('unit_price', 14, 4)->default(0);
            $table->unsignedInteger('carton_qty')->default(0);
            $table->decimal('net_weight', 14, 3)->nullable();
            $table->decimal('gross_weight', 14, 3)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ss_packing_lists', function (Blueprint $table) use ($base, $shipment): void {
            $base($table);
            $table->string('packing_no', 24);
            $shipment($table);
            $table->date('packing_date');
            $table->foreignUlid('invoice_id')->nullable()->constrained('ss_invoices')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->string('state', 10)->default('DRAFT'); // DRAFT | FINAL | CANCELLED
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'packing_no']);
        });

        Schema::create('ss_packing_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('packing_list_id')->constrained('ss_packing_lists')->cascadeOnDelete();
            $table->foreignUlid('shipment_line_id')->constrained('ss_shipment_lines')->restrictOnDelete();
            $table->string('color', 120)->nullable();
            $table->string('size', 60)->nullable();
            $table->unsignedInteger('carton_from');
            $table->unsignedInteger('carton_to');
            $table->decimal('pcs_per_carton', 12, 3);
            $table->decimal('net_weight', 14, 3)->default(0);
            $table->decimal('gross_weight', 14, 3)->default(0);
            $table->decimal('cbm', 12, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ss_doc_handovers', function (Blueprint $table) use ($base, $shipment): void {
            $base($table);
            $table->string('handover_no', 24);
            $table->date('handover_date');
            $shipment($table);
            $table->foreignUlid('invoice_id')->nullable()->constrained('ss_invoices')->nullOnDelete();
            $table->foreignUlid('packing_list_id')->nullable()->constrained('ss_packing_lists')->nullOnDelete();
            $table->string('exp_no', 60)->nullable();
            $table->string('certificate', 120)->nullable();
            $table->string('document_type', 120)->nullable();
            $table->text('document_list')->nullable();
            $table->string('submitted_by', 120)->nullable();
            $table->string('received_by', 120)->nullable();
            $table->string('status', 12)->default('PENDING'); // PENDING | HANDED_OVER | RECEIVED | RETURNED
            $table->foreignUlid('file_id')->nullable();
            $table->string('file_name', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'handover_no']);
        });
    }

    public function down(): void
    {
        foreach (['ss_doc_handovers', 'ss_packing_lines', 'ss_packing_lists', 'ss_invoice_lines', 'ss_invoices', 'ss_containers', 'ss_bookings'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('ss_shipments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['confirmed_at', 'closed_at']);
        });
    }
};
