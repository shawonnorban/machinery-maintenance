<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The paperwork an import runs on (Procurement spec §10).
 *
 * A chain of three: the supplier's proforma invoice is what the bank opens
 * a letter of credit against, and the shipment sails against both.
 *
 * Shapes worth explaining:
 *
 *  - A PI is **versioned** (`supersedes_id`, `version`) rather than edited.
 *    The bank opened its LC against a specific document; overwriting it
 *    would leave the LC matching something that no longer says what it said.
 *  - LC amendments are their own table. A bank charges a fee for each one
 *    and the reason is the story of how the order moved; folding them into
 *    the current amount loses both.
 *  - A shipment keeps `original_eta` **and** `eta`. The gap between what
 *    the forwarder first promised and what happened is the supplier's
 *    delivery record, and a field updated quietly every week holds no
 *    record at all.
 *  - `document_status` is separate from `status`: cloth that has landed but
 *    whose documents are still at the bank cannot be cleared, and "arrived"
 *    on its own would suggest it can be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('pi_no', 64);
            $table->date('pi_date');
            $table->unsignedInteger('version')->default(1);
            // The PI this one replaced, if it replaced one.
            $table->ulid('supersedes_id')->nullable();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 24)->default('DRAFT');
            $table->string('incoterm', 16)->nullable();
            $table->string('port_of_loading', 120)->nullable();
            $table->string('port_of_discharge', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'purchase_order_id'], 'proforma_invoices_order_idx');
            $table->index(['company_id', 'pi_no'], 'proforma_invoices_no_idx');
        });

        // Self-reference after the table exists (Postgres rejects it inline).
        Schema::table('proforma_invoices', function (Blueprint $table): void {
            $table->foreign('supersedes_id', 'proforma_invoices_supersedes_fk')
                ->references('id')->on('proforma_invoices')->nullOnDelete();
        });

        Schema::create('letters_of_credit', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUlid('proforma_invoice_id')->nullable()->constrained('proforma_invoices')->nullOnDelete();
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('lc_no', 64);
            $table->date('lc_date');
            $table->string('bank_name', 160);
            $table->string('branch', 160)->nullable();
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('USD');
            $table->date('expiry_date')->nullable();
            $table->date('last_shipment_date')->nullable();
            $table->unsignedInteger('tenor_days')->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'lc_no'], 'letters_of_credit_no_unique');
            $table->index(['company_id', 'purchase_order_id'], 'letters_of_credit_order_idx');
            // The expiring-LC sweep reads exactly this.
            $table->index(['company_id', 'status', 'expiry_date'], 'letters_of_credit_expiry_idx');
        });

        Schema::create('letter_of_credit_amendments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('letter_of_credit_id')->constrained('letters_of_credit')->cascadeOnDelete();
            $table->unsignedInteger('amendment_no');
            $table->date('amendment_date');
            $table->decimal('previous_amount', 18, 4)->default(0);
            $table->decimal('new_amount', 18, 4)->default(0);
            $table->date('previous_expiry_date')->nullable();
            $table->date('new_expiry_date')->nullable();
            // What the bank charged for it.
            $table->decimal('bank_charge', 18, 4)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->text('reason');
            $table->foreignUlid('amended_by')->nullable();
            $table->timestamps();

            $table->unique(['letter_of_credit_id', 'amendment_no'], 'lc_amendments_no_unique');
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignUlid('proforma_invoice_id')->nullable()->constrained('proforma_invoices')->nullOnDelete();
            $table->foreignUlid('letter_of_credit_id')->nullable()->constrained('letters_of_credit')->nullOnDelete();
            $table->foreignUlid('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('shipment_no', 32);
            // SEA | AIR | ROAD | COURIER
            $table->string('mode', 16)->default('SEA');
            $table->string('bl_awb_no', 64)->nullable();
            $table->string('container_no', 64)->nullable();
            $table->string('vessel_or_flight', 120)->nullable();

            $table->date('shipping_date')->nullable();
            // What was first promised, and what is promised now.
            $table->date('original_eta')->nullable();
            $table->date('eta')->nullable();
            $table->date('actual_arrival_date')->nullable();
            $table->date('cleared_date')->nullable();

            $table->string('port_of_loading', 120)->nullable();
            $table->string('port_of_discharge', 120)->nullable();
            $table->string('status', 24)->default('PLANNED');
            // Where the papers are — separate from where the goods are.
            $table->string('document_status', 24)->default('AWAITED');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'shipment_no'], 'shipments_no_unique');
            $table->index(['company_id', 'purchase_order_id'], 'shipments_order_idx');
            // The "what lands this week / what is late" screen.
            $table->index(['company_id', 'status', 'eta'], 'shipments_eta_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('letter_of_credit_amendments');
        Schema::dropIfExists('letters_of_credit');

        Schema::table('proforma_invoices', function (Blueprint $table): void {
            $table->dropForeign('proforma_invoices_supersedes_fk');
        });

        Schema::dropIfExists('proforma_invoices');
    }
};
