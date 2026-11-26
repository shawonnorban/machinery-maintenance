<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Production (AnnotechERP 15/04), the floor's own chain:
 *
 * - `knit_prod_orders`: a fabric production order (Bulk, Yarn-Dyed, Sample,
 *   Flat Bed Rib) — one order's fabric and colour, what is owed (Req Qty ±
 *   Adjusted) and what the yarn store holds against it.
 * - `knit_programs`: Knit Programmes (KP) released against an order; never
 *   more than it still owes.
 * - `knit_cards`: Knit Cards (KC) cut off a programme; what a machine is
 *   loaded with and every roll is weighed against.
 * - `knit_roll_sheets` + `knit_rolls`: Manual Roll Production Entry — rolls
 *   off a card, numbered 1, 2, 3 per card, weighed and deducted.
 * - `knit_roll_inspections` + lines: Grey Roll Quality Inspection — the
 *   four-point sheet of one roll; its decision writes the roll's quality.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_prod_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('po_no', 24);
            // BULK, YD, SAMPLE, FLAT_BED_RIB.
            $table->string('order_kind', 16);
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('category_code', 16)->nullable();
            $table->string('ship_month', 7)->nullable();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->string('fabric_desc', 160);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('finish_dia', 24)->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->string('item_image', 255)->nullable();
            $table->string('booking_ref', 80)->nullable();
            $table->decimal('req_qty', 14, 3);
            $table->decimal('adjusted_qty', 14, 3)->default(0);
            $table->decimal('saving_target_qty', 14, 3)->nullable();
            $table->decimal('yarn_stock_qty', 14, 3)->nullable();
            // RUNNING, COMPLETED, HOLD, CLOSED, CANCELLED.
            $table->string('status', 12)->default('RUNNING');
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'po_no']);
            $table->index(['company_id', 'order_kind', 'status']);
        });

        Schema::create('knit_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kp_no', 24);
            $table->foreignUlid('prod_order_id')->constrained('knit_prod_orders')->cascadeOnDelete();
            $table->date('program_date');
            $table->decimal('qty', 14, 3);
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->text('remarks')->nullable();
            // OPEN, RUNNING, COMPLETED, CLOSED, CANCELLED.
            $table->string('status', 12)->default('OPEN');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'kp_no']);
        });

        Schema::create('knit_cards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kc_no', 24);
            $table->foreignUlid('program_id')->constrained('knit_programs')->cascadeOnDelete();
            $table->foreignUlid('prod_order_id')->constrained('knit_prod_orders')->cascadeOnDelete();
            $table->date('card_date');
            $table->decimal('assigned_qty', 14, 3);
            $table->string('colour_name', 80)->nullable();
            $table->string('machine_dia', 24)->nullable();
            $table->string('finish_dia', 24)->nullable();
            $table->string('gauge', 24)->nullable();
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->string('yarn_details', 255)->nullable();
            // The machine its last roll came off.
            $table->foreignUlid('machine_id')->nullable()->constrained('knit_machines')->nullOnDelete();
            // OPEN, RUNNING, COMPLETED, CLOSED, CANCELLED.
            $table->string('status', 12)->default('OPEN');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'kc_no']);
        });

        Schema::create('knit_roll_sheets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('sheet_no', 24);
            $table->foreignUlid('card_id')->constrained('knit_cards')->restrictOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'sheet_no']);
        });

        Schema::create('knit_rolls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('sheet_id')->constrained('knit_roll_sheets')->cascadeOnDelete();
            $table->foreignUlid('card_id')->constrained('knit_cards')->restrictOnDelete();
            $table->unsignedInteger('roll_no');
            // KC-2026100001/1, what is written on the tag.
            $table->string('roll_code', 32);
            $table->dateTime('roll_at');
            $table->foreignUlid('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignUlid('operator_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignUlid('sc_party_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->foreignUlid('machine_id')->nullable()->constrained('knit_machines')->nullOnDelete();
            $table->decimal('weight', 12, 3);
            $table->decimal('deducted', 12, 3)->default(0);
            // IN_KNITTING, GREY_STORE, BATCH_STORE, LEFT_OVER, DELIVERED.
            $table->string('movement', 12)->default('IN_KNITTING');
            // PENDING, PASSED, HOLD, REJECTED.
            $table->string('quality', 12)->default('PENDING');
            $table->string('label_info', 80)->nullable();
            $table->string('comments', 300)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'card_id', 'roll_no']);
            $table->index(['company_id', 'roll_at']);
        });

        Schema::create('knit_roll_inspections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('qci_no', 24);
            $table->foreignUlid('roll_id')->constrained('knit_rolls')->cascadeOnDelete();
            $table->date('inspected_on');
            $table->foreignUlid('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignUlid('qc_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->decimal('deduct_weight', 12, 3)->default(0);
            // A grade set by hand overrules the one the points come to.
            $table->string('grade_override', 1)->nullable();
            $table->unsignedInteger('total_points')->default(0);
            $table->decimal('grade_point', 10, 2)->default(0);
            $table->string('grade', 1);
            $table->text('comments')->nullable();
            // DRAFT, PASSED, HOLD, REJECTED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'qci_no']);
            $table->unique(['company_id', 'roll_id']);
        });

        Schema::create('knit_roll_inspection_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('inspection_id')->constrained('knit_roll_inspections')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('defect', 32);
            // The defect's score when it was found, so re-scoring it later cannot re-grade delivered fabric.
            $table->unsignedSmallInteger('points_each');
            $table->unsignedInteger('count');
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_roll_inspection_lines', 'knit_roll_inspections', 'knit_rolls', 'knit_roll_sheets', 'knit_cards', 'knit_programs', 'knit_prod_orders'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
