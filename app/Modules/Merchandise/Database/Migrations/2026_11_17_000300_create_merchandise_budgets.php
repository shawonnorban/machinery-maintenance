<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costing & Budget → Order Budget Sheet (AnnotechERP 06/09): one budget per
 * order (BGT-2026-000001), per piece in the order's currency — the bill of
 * material in six sections (yarn, purchased fabric, sewing, finishing,
 * general and special accessories), the fabric process cost heads and the
 * overall cost heads up to FOB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_budgets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('budget_no', 24);
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->foreignUlid('style_id')->nullable()->constrained('merch_styles')->nullOnDelete();
            $table->foreignUlid('buyer_office_id')->nullable()->constrained('buyer_offices')->nullOnDelete();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->json('process_costs')->nullable();
            $table->json('overall_costs')->nullable();
            $table->string('remarks', 2000)->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'budget_no']);
            $table->unique('order_id');
        });

        Schema::create('merch_budget_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained('merch_budgets')->cascadeOnDelete();
            $table->string('section', 24);
            $table->string('description', 255);
            $table->string('uom', 16)->nullable();
            $table->decimal('qty', 14, 4)->default(0);
            $table->decimal('rate', 14, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_budget_lines');
        Schema::dropIfExists('merch_budgets');
    }
};
