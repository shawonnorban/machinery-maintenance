<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Yarn Procurement (AnnotechERP 15/02), as Brickland keeps it:
 *
 * - `knit_yarn_requisitions` + lines: Yarn Purchase Indent — the yarn an
 *   order needs, line by line, split into what is bought locally and what is
 *   imported; numbered YR-2026100001, Draft → Waiting Approval → Approved or
 *   Rejected, then Ordered once a purchase order is placed against it.
 * - `knit_yarn_comparisons` + lines + offers: Yarn Comparative Statement —
 *   the spinners' quotations laid against an approved requisition's lines,
 *   and the spinner each line is finally bought from.
 * - `knit_yarn_pos` + lines: Yarn Purchase Order — what is bought from one
 *   spinner, at what rate, on what terms, received against by the store.
 *
 * A yarn line names its item (a fibre composition), count, colour, brand and
 * unit, as Brickland's sheets do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_yarn_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('requisition_no', 24);
            $table->date('requisition_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->date('required_date')->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, WAITING_APPROVAL, APPROVED, REJECTED, ORDERED, CLOSED, CANCELLED.
            $table->string('status', 20)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'requisition_no']);
            $table->index(['company_id', 'status', 'requisition_date']);
        });

        Schema::create('knit_yarn_requisition_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('knit_yarn_requisitions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('uom', 16);
            $table->decimal('qty_local', 14, 4)->default(0);
            $table->decimal('qty_import', 14, 4)->default(0);
            $table->date('knit_start_date')->nullable();
            $table->date('ship_date')->nullable();
            $table->decimal('booking_price', 14, 4)->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_comparisons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('cs_no', 24);
            $table->date('cs_date');
            $table->foreignUlid('requisition_id')->constrained('knit_yarn_requisitions')->restrictOnDelete();
            // Copied from the requisition on save, so the sheet still shows the paper it was raised from.
            $table->date('requisition_date')->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, FINALIZED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'cs_no']);
            $table->index(['company_id', 'status', 'cs_date']);
        });

        Schema::create('knit_yarn_comparison_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('comparison_id')->constrained('knit_yarn_comparisons')->cascadeOnDelete();
            $table->unsignedSmallInteger('sl_no');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('uom', 16);
            $table->decimal('qty_local', 14, 4)->default(0);
            $table->decimal('qty_import', 14, 4)->default(0);
            $table->decimal('present_stock', 14, 4)->default(0);
            $table->decimal('pipeline', 14, 4)->default(0);
            $table->date('knit_start_date')->nullable();
            $table->date('ship_date')->nullable();
            $table->decimal('booking_price', 14, 4)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            // Finalized With: the spinner this line is bought from.
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_offers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('comparison_id')->constrained('knit_yarn_comparisons')->cascadeOnDelete();
            $table->unsignedSmallInteger('sl_no');
            // LOCAL or IMPORT: which of the two printed price bands the offer belongs to.
            $table->string('source', 8);
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->decimal('price', 14, 4);
            $table->foreignUlid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('conf_qty', 14, 4)->nullable();
            $table->unsignedSmallInteger('lead_days')->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_pos', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('po_no', 24);
            $table->date('po_date');
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('comparison_id')->nullable()->constrained('knit_yarn_comparisons')->nullOnDelete();
            $table->foreignUlid('requisition_id')->nullable()->constrained('knit_yarn_requisitions')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('source', 8);
            $table->string('method', 8);
            $table->string('inco_term', 8)->nullable();
            $table->string('payment_term', 12)->nullable();
            $table->string('ship_mode', 8)->nullable();
            $table->foreignUlid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('conv_rate', 14, 6)->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('shipment_date')->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, PLACED, PART_RECEIVED, RECEIVED, CLOSED, CANCELLED.
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('placed_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'po_no']);
            $table->index(['company_id', 'status', 'po_date']);
        });

        Schema::create('knit_yarn_po_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('po_id')->constrained('knit_yarn_pos')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->string('uom', 16);
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4);
            // Kept in step by the yarn store's receipts.
            $table->decimal('received_qty', 14, 4)->default(0);
            $table->date('knit_start_date')->nullable();
            $table->date('ship_date')->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knit_yarn_po_lines');
        Schema::dropIfExists('knit_yarn_pos');
        Schema::dropIfExists('knit_yarn_offers');
        Schema::dropIfExists('knit_yarn_comparison_lines');
        Schema::dropIfExists('knit_yarn_comparisons');
        Schema::dropIfExists('knit_yarn_requisition_lines');
        Schema::dropIfExists('knit_yarn_requisitions');
    }
};
