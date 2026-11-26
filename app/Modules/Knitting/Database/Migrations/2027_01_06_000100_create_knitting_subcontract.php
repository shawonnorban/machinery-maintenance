<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Production (AnnotechERP 15/04), sub-contract knitting:
 *
 * - `knit_sc_programs` + lines: a knitting programme placed with a
 *   sub-contract knitter — Bulk (Solid), Sample (Solid) or Party (Y/D);
 *   one line per fabric and shade, what the party is asked to knit and is
 *   paid per kilogram. Draft → Submitted → Approved / Rejected.
 * - `knit_sc_receipts` + fabrics + yarns: a programme received from a party
 *   — what they want knitted, shade by shade (each fabric line a serial),
 *   and the yarn their store issues against each serial. Draft → Confirmed
 *   → In Production → Closed; Cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_sc_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 24);
            // BULK_SOLID, SAMPLE_SOLID, PARTY_YD.
            $table->string('kind', 16);
            // BULK, SHORT_BULK, DEVELOP, SAMPLE.
            $table->string('program_type', 12);
            $table->date('po_date');
            $table->date('target_date')->nullable();
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('category_code', 16)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('style_ref', 64)->nullable();
            $table->string('work_style_no', 64)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, SUBMITTED, APPROVED, REJECTED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->string('decision_note', 255)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
            $table->index(['company_id', 'kind', 'status']);
        });

        Schema::create('knit_sc_program_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('knit_sc_programs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('fabric', 160);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('machine_dia', 24)->nullable();
            $table->string('gauge', 24)->nullable();
            $table->string('finish_dia', 24)->nullable();
            $table->string('gsm', 24)->nullable();
            $table->decimal('qty', 14, 3);
            $table->decimal('rate', 14, 4)->nullable();
            $table->string('uom', 16);
            // Kept by the out-house receive desk (Fabric Store, 15/05).
            $table->decimal('received_qty', 14, 3)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_sc_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 32);
            $table->boolean('auto_number')->default(true);
            $table->date('prog_date');
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('yarn_store_id')->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT, CONFIRMED, IN_PRODUCTION, CLOSED, CANCELLED.
            $table->string('status', 16)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
            $table->index(['company_id', 'status', 'prog_date']);
        });

        Schema::create('knit_sc_receipt_fabrics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_sc_receipts')->cascadeOnDelete();
            // Fab. Ord. SL — what the yarn rows hang off.
            $table->unsignedSmallInteger('sl');
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('party_ref', 64)->nullable();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('qty', 14, 3);
            $table->string('uom', 16);
            $table->decimal('finish_dia', 8, 2)->nullable();
            $table->string('dia_unit', 4)->nullable();
            $table->string('width_type', 4)->nullable();
            $table->string('yd_type', 20)->nullable();
            $table->unsignedSmallInteger('feeder')->nullable();
            $table->foreignUlid('mc_dia_id')->nullable()->constrained('knit_ref_entries')->nullOnDelete();
            $table->foreignUlid('gauge_id')->nullable()->constrained('knit_ref_entries')->nullOnDelete();
            $table->string('rate_type', 8)->nullable();
            $table->decimal('rate', 14, 4)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
            $table->unique(['receipt_id', 'sl']);
        });

        Schema::create('knit_sc_receipt_yarns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('knit_sc_receipts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->unsignedSmallInteger('sl');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('fab_part', 60)->nullable();
            $table->string('colour_group', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('stitch_length', 8, 3)->nullable();
            $table->decimal('elastane_pct', 6, 2)->nullable();
            $table->decimal('ratio_qty', 8, 3)->nullable();
            $table->decimal('required_qty', 14, 3)->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_sc_receipt_yarns', 'knit_sc_receipt_fabrics', 'knit_sc_receipts', 'knit_sc_program_lines', 'knit_sc_programs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
