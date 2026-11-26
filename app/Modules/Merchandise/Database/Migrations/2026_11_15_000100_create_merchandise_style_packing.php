<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item/Combo/Model → Packing Instruction (AnnotechERP 06/05): how a style's
 * orders are packed — per SKU, a single-piece pack or a multi-piece pack of a
 * uniform or random ratio, how many pieces a pack holds, for which order,
 * garment item and colour (or all colours).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_style_packing', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->string('sku_ref', 64);
            $table->string('pack_type', 16)->default('SINGLE');
            $table->unsignedInteger('pcs_per_pack')->default(1);
            $table->foreignUlid('order_id')->constrained('merch_orders')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('merch_style_items')->cascadeOnDelete();
            $table->boolean('all_colours')->default(false);
            $table->foreignUlid('colour_id')->nullable()->constrained('merch_style_colours')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'style_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_packing');
    }
};
