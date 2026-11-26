<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dye House → S/C Dyeing (AnnotechERP 17/09): dyeing placed with an outside
 * dye house.
 *
 * - `dye_sc_programmes` + shades: Out-House Sub-Contract Programme
 *   (DF-SC-OH-YYYYMM####) — the party, store and dates, and the shades to dye
 *   (buyer, order, colour, kilos).
 * - `dye_sc_requisitions` + lines: Sub-Contract Dye Batch Programme
 *   (DF-SC-RQ-…, its batch DF-SC-B-…) — grey fabric called off against a
 *   programme's shades, and what the store has issued; the Sub-Contract Grey
 *   Fabric Indent List reads these.
 * - `dye_sc_challans` + programmes: Out-House Delivery Challan (DF-SC-CH-…) —
 *   the programmes despatched to the party on one challan.
 * - `dye_sc_receipts` + lines: Out-House Floor Receipt (DF-SC-FR-…, the
 *   floor's own check, QC No) and Challan Receipt (DF-SC-MRR-…, booked into
 *   the store, Batch No) — dyed fabric coming back, per programme.
 * - `dye_sc_bills`: Out-House Process Bill (DF-SC-BL-…) — the party's bill
 *   and its revision trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dye_sc_programmes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 24);
            $table->date('issue_date');
            $table->date('exp_delivery_date')->nullable();
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            // DRAFT, SUBMITTED, PARAMS_UPDATED, DESPATCHED, COMPLETED, CANCELLED.
            $table->string('status', 16);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
            $table->index(['company_id', 'issue_date']);
        });
        Schema::create('dye_sc_programme_shades', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('programme_id')->constrained('dye_sc_programmes')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->decimal('req_kg', 14, 3);
            $table->string('remarks', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_sc_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->string('batch_no', 24);
            $table->date('req_date');
            $table->foreignUlid('programme_id')->constrained('dye_sc_programmes')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            // WAITING, PARTIAL, ISSUED.
            $table->string('status', 10)->default('WAITING');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
            $table->unique(['company_id', 'batch_no']);
        });
        Schema::create('dye_sc_requisition_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('dye_sc_requisitions')->cascadeOnDelete();
            $table->foreignUlid('shade_id')->constrained('dye_sc_programme_shades')->restrictOnDelete();
            $table->decimal('req_kg', 14, 3);
            $table->decimal('issued_kg', 14, 3)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_sc_challans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('challan_date');
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
        });
        Schema::create('dye_sc_challan_programmes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('challan_id')->constrained('dye_sc_challans')->cascadeOnDelete();
            $table->foreignUlid('programme_id')->constrained('dye_sc_programmes')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            // A programme goes out on one challan.
            $table->unique('programme_id');
        });
        Schema::create('dye_sc_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // FLOOR (DF-SC-FR-) or STORE (DF-SC-MRR-).
            $table->string('kind', 6);
            $table->string('mrr_no', 24);
            $table->date('issue_date');
            $table->foreignUlid('challan_id')->constrained('dye_sc_challans')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('transfer_party_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('batch_no', 40)->nullable();
            $table->string('qc_no', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mrr_no']);
            $table->index(['company_id', 'kind', 'issue_date']);
        });
        Schema::create('dye_sc_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('dye_sc_receipts')->cascadeOnDelete();
            $table->foreignUlid('programme_id')->constrained('dye_sc_programmes')->restrictOnDelete();
            $table->decimal('qty_kg', 14, 3);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_sc_bills', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('bill_no', 24);
            $table->date('bill_date');
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            // A revision names the bill it revises; its number counts the trail.
            $table->ulid('ref_bill_id')->nullable();
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->string('revision_descr', 255)->nullable();
            // DRAFT, WAITING_STORE, APPROVED, COMPLETED, CANCELLED.
            $table->string('status', 14);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'bill_no']);
            $table->index(['company_id', 'supplier_id']);
        });
        // The trail points back into the same table, so its key is added once the table is there.
        Schema::table('dye_sc_bills', function (Blueprint $table): void {
            $table->foreign('ref_bill_id')->references('id')->on('dye_sc_bills')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['dye_sc_bills', 'dye_sc_receipt_lines', 'dye_sc_receipts', 'dye_sc_challan_programmes', 'dye_sc_challans', 'dye_sc_requisition_lines', 'dye_sc_requisitions',
            'dye_sc_programme_shades', 'dye_sc_programmes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
