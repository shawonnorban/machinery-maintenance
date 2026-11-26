<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget → Accessories Budget Pending / BOM List (AnnotechERP 33/34,
 * Norban's Accessories Pre-Budget, user 2026-11-19): a budget's accessories
 * BOMs. One BOM holds one or more of the budget's styles (a style is in one
 * BOM at a time); each style has its own accessory lines, so a style's
 * consumption can differ. Numbered after the budget: PB-…-A1, A2 … Order Qty,
 * FOB, brand, job no and artwork come from the budget's requisitions each
 * time; a line keeps what was typed (item, code, supplier, nominated, item
 * order qty, extra %, rate, unit, body / item consumption, remarks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pb_boms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained('pb_budgets')->cascadeOnDelete();
            $table->unsignedInteger('seq');
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('finalized_at')->nullable();
            $table->ulid('finalized_by')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['budget_id', 'seq']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('pb_bom_styles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bom_id')->constrained('pb_boms')->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained('pb_budgets')->cascadeOnDelete();
            $table->string('style', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['budget_id', 'style']);
        });

        Schema::create('pb_bom_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bom_style_id')->constrained('pb_bom_styles')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUlid('accessory_item_id')->nullable()->constrained('pb_accessory_items')->nullOnDelete();
            $table->string('item_name', 255)->nullable();
            $table->string('item_code', 120)->nullable();
            $table->string('supplier', 255)->nullable();
            $table->string('nominated', 20)->default('NOMINATED');
            $table->decimal('order_qty', 14, 2)->nullable();
            $table->decimal('extra_percent', 8, 2)->default(2);
            $table->decimal('rate', 18, 8)->nullable();
            $table->string('uom', 20)->nullable();
            $table->decimal('body_cons', 14, 4)->nullable();
            $table->decimal('item_cons', 14, 4)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamps();
            $table->index(['bom_style_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_bom_lines');
        Schema::dropIfExists('pb_bom_styles');
        Schema::dropIfExists('pb_boms');
    }
};
