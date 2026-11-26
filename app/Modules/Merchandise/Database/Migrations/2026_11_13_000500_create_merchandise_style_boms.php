<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item/Combo/Model → BOM (AnnotechERP 06/05): one bill of materials per style,
 * numbered BOM-000001, kept in versions. A version is a draft until approved;
 * the newest approved version is the one in use, and a change after approval
 * opens the next version as a draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_style_boms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->string('bom_no', 16);
            $table->timestamps();
            $table->unique(['company_id', 'bom_no']);
            $table->unique('style_id');
        });

        Schema::create('merch_style_bom_versions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bom_id')->constrained('merch_style_boms')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('DRAFT');
            $table->unsignedInteger('order_qty')->nullable();
            $table->decimal('addl_pct', 6, 2)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->ulid('approved_by')->nullable();
            $table->timestamps();
            $table->unique(['bom_id', 'version']);
        });

        Schema::create('merch_style_bom_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('version_id')->constrained('merch_style_bom_versions')->cascadeOnDelete();
            $table->string('item_name', 191);
            $table->string('cons_type', 16)->nullable();
            $table->decimal('consumption', 14, 4)->nullable();
            $table->string('cons_unit', 8)->nullable();
            $table->decimal('per_gmt', 10, 2)->nullable();
            $table->string('purchase_unit', 8)->nullable();
            $table->decimal('conversion', 14, 4)->nullable();
            $table->boolean('apply_addl')->default(false);
            $table->decimal('rate', 14, 4)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->string('category', 24)->nullable();
            $table->string('item_code', 64)->nullable();
            $table->decimal('allow_pct', 6, 2)->nullable();
            $table->string('supplier', 191)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_bom_lines');
        Schema::dropIfExists('merch_style_bom_versions');
        Schema::dropIfExists('merch_style_boms');
    }
};
