<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item/Combo/Model → Yarn/Fabric Mapping (AnnotechERP 06/05): which of the
 * style's fabrics each garment part of an item is cut from, for which garment
 * colour (or all of them), in which fabric colour and at what share, and for
 * which production categories. A fabric or colour mapped here stays on the
 * style until the mapping lets it go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_style_fabric_maps', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->json('prod_categories');
            $table->foreignUlid('item_id')->constrained('merch_style_items')->cascadeOnDelete();
            $table->foreignUlid('garment_part_id')->constrained('garment_parts')->restrictOnDelete();
            $table->foreignUlid('fabric_id')->constrained('merch_style_fabrics')->restrictOnDelete();
            $table->boolean('all_colours')->default(false);
            $table->foreignUlid('gmt_colour_id')->nullable()->constrained('merch_style_colours')->restrictOnDelete();
            $table->foreignUlid('fab_colour_id')->nullable()->constrained('merch_style_colours')->restrictOnDelete();
            $table->decimal('ratio', 5, 2)->default(100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'style_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_fabric_maps');
    }
};
