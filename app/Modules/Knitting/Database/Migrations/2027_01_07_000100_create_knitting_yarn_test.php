<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Production (AnnotechERP 15/04), the yarn test chain:
 *
 * - `knit_yarn_test_orders` + lines: Knit Plan & Requisition for Yarn Test —
 *   the lab's own requisition, one line per lot going to the lab. Waiting
 *   for Issue → Issued (the store issues it) → Tested; Cancelled.
 * - `knit_yarn_tests`: what the lab measured, filed against one test order
 *   (filing again replaces it).
 * - `knit_yarn_test_results`: the register — one row per lot answered, from
 *   the lab (LAB, the test's lines) or released without a measurement
 *   (WAIVED, Approval Without Testing, on the Test By's authority).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_yarn_test_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('order_no', 24);
            $table->date('order_date');
            $table->string('req_type', 32)->default('YARN_TEST');
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignUlid('requested_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignUlid('attn_to')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->string('yarn_description', 200);
            $table->string('knit_card_no', 40)->nullable();
            $table->text('remarks')->nullable();
            // WAITING_ISSUE, ISSUED, TESTED, CANCELLED.
            $table->string('status', 16)->default('WAITING_ISSUE');
            $table->timestamp('issued_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'order_no']);
            $table->index(['company_id', 'status', 'order_date']);
        });

        Schema::create('knit_yarn_test_order_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('knit_yarn_test_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('lc_no', 40)->nullable();
            $table->string('lot_no', 40);
            $table->decimal('qty', 14, 3)->nullable();
            $table->string('uom', 16)->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_tests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('test_no', 24);
            $table->foreignUlid('order_id')->constrained('knit_yarn_test_orders')->restrictOnDelete();
            $table->date('test_date');
            $table->foreignUlid('test_by')->constrained('hr_employees')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'test_no']);
            $table->unique('order_id');
        });

        Schema::create('knit_yarn_test_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // LAB (a test's line) or WAIVED (released without a measurement).
            $table->string('basis', 8);
            $table->foreignUlid('test_id')->nullable()->constrained('knit_yarn_tests')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->date('test_date');
            $table->foreignUlid('test_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('lc_no', 40)->nullable();
            $table->string('lot_no', 40);
            $table->string('colour', 40)->nullable();
            // PENDING, PASSED, FAILED, CONDITIONAL.
            $table->string('result', 12);
            $table->string('note', 300)->nullable();
            // The store's lot a waiver released.
            $table->foreignUlid('yarn_lot_id')->nullable()->constrained('knit_yarn_lots')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'lot_no']);
            $table->index(['company_id', 'basis', 'test_date']);
        });
    }

    public function down(): void
    {
        foreach (['knit_yarn_test_results', 'knit_yarn_tests', 'knit_yarn_test_order_lines', 'knit_yarn_test_orders'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
