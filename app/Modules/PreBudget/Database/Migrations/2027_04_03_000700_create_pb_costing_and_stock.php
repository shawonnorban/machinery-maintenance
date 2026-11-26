<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget → Knit/Dying Costing, Stock Yarn Use (AnnotechERP 33/34, plan
 * step 4; Norban's costing and store stock entry):
 *
 *  - the budget's Dollar Rate, the two cost notes, and when its costing and
 *    its stock were completed — with what the budget was then (`*_basis`):
 *    when its fabrications / styles / lines change afterwards, that part is
 *    open again;
 *  - pb_budget_costs: a fabrication's Knitting / Dyeing Finishing / Yarn
 *    Dyeing / AOP rate (BDT per kg), a style's Embellishment and CM (USD per
 *    dozen);
 *  - pb_budget_badges: the Badge Cost rows (user, 2026-11-19): styles, order
 *    qty, wastage, cost per dozen, supplier, remarks;
 *  - pb_stock_lots: the stock lots a budget line uses (contracts, yarn mill,
 *    origin, LC, lot, qty, price) — and a line marked "No stock".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pb_budgets', function (Blueprint $table): void {
            $table->decimal('dollar_rate', 10, 4)->nullable()->after('discount_percent');
            $table->text('knit_note')->nullable()->after('dollar_rate');
            $table->text('dye_note')->nullable()->after('knit_note');
            $table->timestamp('costing_done_at')->nullable();
            $table->foreignUlid('costing_done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('costing_basis', 64)->nullable();
            $table->timestamp('stock_done_at')->nullable();
            $table->foreignUlid('stock_done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stock_basis', 64)->nullable();
        });

        Schema::table('pb_budget_lines', function (Blueprint $table): void {
            $table->boolean('no_stock')->default(false)->after('remarks');
        });

        Schema::create('pb_budget_costs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained('pb_budgets')->cascadeOnDelete();
            $table->string('kind', 8); // FABRIC (key = fabrication) | STYLE (key = requisition id)
            $table->string('cost_key', 600);
            $table->decimal('knit_rate', 12, 4)->nullable();      // BDT / kg
            $table->decimal('dye_rate', 12, 4)->nullable();       // BDT / kg
            $table->decimal('yarn_dye_rate', 12, 4)->nullable();  // BDT / kg
            $table->decimal('aop_rate', 12, 4)->nullable();       // BDT / kg
            $table->decimal('emb_per_dz', 12, 4)->nullable();     // USD / dozen
            $table->decimal('cm_per_dz', 12, 4)->nullable();      // USD / dozen
            $table->timestamps();
            $table->unique(['budget_id', 'kind', 'cost_key']);
        });

        Schema::create('pb_budget_badges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained('pb_budgets')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->jsonb('styles');                               // requisition ids
            $table->decimal('order_qty', 14, 2)->nullable();       // null: the styles' Order Qty
            $table->decimal('wastage', 8, 4)->nullable();          // a factor: 1.030 = 3 %
            $table->decimal('cost_per_dz', 12, 4)->nullable();     // USD
            $table->string('supplier', 255)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('pb_stock_lots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained('pb_budgets')->cascadeOnDelete();
            $table->string('section', 8);
            $table->string('line_key', 600);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('purchase_contract_no', 120)->nullable();
            $table->string('stock_contract_no', 120)->nullable();
            $table->string('mill', 255)->nullable();               // Supplier Master
            $table->string('origin', 8)->nullable();               // LOCAL | IMPORT
            $table->string('lc_no', 120)->nullable();
            $table->string('lot_no', 120)->nullable();
            $table->decimal('qty', 16, 3);
            $table->decimal('price', 14, 4)->nullable();
            $table->timestamps();
            $table->index(['budget_id', 'section', 'line_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_stock_lots');
        Schema::dropIfExists('pb_budget_badges');
        Schema::dropIfExists('pb_budget_costs');
        Schema::table('pb_budget_lines', fn (Blueprint $t) => $t->dropColumn('no_stock'));
        Schema::table('pb_budgets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('costing_done_by');
            $table->dropConstrainedForeignId('stock_done_by');
            $table->dropColumn(['dollar_rate', 'knit_note', 'dye_note', 'costing_done_at', 'costing_basis', 'stock_done_at', 'stock_basis']);
        });
    }
};
