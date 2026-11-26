<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget → Create Budget (AnnotechERP 33/34, plan 2026-11-19): a budget
 * made from completed requisitions of one buyer, numbered on its first save
 * the Norban way (PB-<buyer>-<MMYY>-<seq>). Its lines are not stored as a
 * picture: Knit lines (Yarn Count + Composition) and Woven lines (Style +
 * Composition + Fabrication) are worked out from its requisitions each time
 * (so a requisition changed before Finalize changes the budget); only what is
 * typed on a line is kept — Need to Purchase when changed, PI Qty, the
 * suppliers' prices, the chosen price, remarks and what to do with a
 * shortage. Revisions (R1, R2) are new rows on the same number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pb_budgets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('budget_no', 40);
            $table->unsignedInteger('budget_seq');
            $table->unsignedSmallInteger('revision_no')->default(0);
            $table->ulid('parent_id')->nullable(); // the version a revision came from (its key added below, once the table has its primary key)
            $table->string('status', 12)->default('DRAFT'); // DRAFT | PENDING | APPROVED | RETURNED
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('buyer_name', 120)->nullable();
            $table->string('contract_no', 120)->nullable();
            $table->decimal('discount_percent', 8, 4)->default(0);
            $table->jsonb('suppliers'); // {YARN: [names], WOVEN: [names]} — the supplier price columns, in order
            $table->unsignedInteger('version')->default(1);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'budget_no', 'revision_no']);
            $table->index(['company_id', 'status']);
        });

        Schema::table('pb_budgets', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('pb_budgets')->nullOnDelete();
        });

        Schema::create('pb_budget_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained('pb_budgets')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('pb_requisitions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['budget_id', 'requisition_id']);
            $table->index(['company_id', 'requisition_id']);
        });

        Schema::create('pb_budget_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained('pb_budgets')->cascadeOnDelete();
            $table->string('section', 8); // YARN | WOVEN
            $table->string('line_key', 600); // what the line stands for: yarn count + composition, or style + composition + fabrication
            $table->decimal('need_to_purchase', 16, 3)->nullable(); // null: worked out (Total Req − stock, up to whole kg / yd)
            $table->decimal('pi_qty', 16, 3)->nullable(); // null: the Need to Purchase
            $table->decimal('price', 14, 4)->nullable(); // null: the lowest supplier price
            $table->jsonb('supplier_prices')->nullable(); // supplier name → price
            $table->string('shortage_action', 12)->nullable(); // PURCHASE | NOT_PURCHASE (a shortage)
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
            $table->unique(['budget_id', 'section', 'line_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_budget_lines');
        Schema::dropIfExists('pb_budget_requisitions');
        Schema::dropIfExists('pb_budgets');
    }
};
