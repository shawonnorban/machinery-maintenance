<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item/Combo/Model → Fabrication (AnnotechERP 06/05): the fabrics a style is
 * cut from — for which items and garment parts, its category, manufactured or
 * purchased, composition, spin and fibre features, fabric type, weight band,
 * width, supplier, the yarn counts of its top, binding and back, wash and
 * finish — and the knitting / yarn-dyed instructions (stripe repeats): a
 * title, pictures, the garment colours they apply to and colour lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_style_fabrics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->json('item_ids')->nullable();
            $table->json('garment_part_ids')->nullable();
            $table->string('fabric_code', 32)->nullable();
            $table->string('fabric_category', 16);
            $table->string('source', 16)->default('MANUFACTURED');
            $table->foreignUlid('fiber_composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->string('spin_type', 16)->nullable();
            $table->boolean('is_slub')->default(false);
            $table->boolean('is_melange')->default(false);
            $table->boolean('is_main')->default(false);
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->decimal('weight_from', 8, 2)->nullable();
            $table->decimal('weight_to', 8, 2)->nullable();
            $table->string('weight_unit', 8)->nullable();
            $table->string('width', 64)->nullable();
            $table->string('supplier', 128)->nullable();
            $table->string('remarks', 500)->nullable();
            // Top, binding and back: each a count and its share.
            $table->json('counts')->nullable();
            $table->json('wash_types')->nullable();
            $table->boolean('wash_na')->default(false);
            $table->json('finish_types')->nullable();
            $table->boolean('finish_na')->default(false);
            $table->text('other_spec')->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'style_id']);
        });

        Schema::create('merch_style_knit_instructions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->string('image_title', 128)->nullable();
            $table->json('image_ids')->nullable();
            $table->json('gmt_colour_ids')->nullable();
            // Each line: a colour, a value and its unit.
            $table->json('lines')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'style_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_knit_instructions');
        Schema::dropIfExists('merch_style_fabrics');
    }
};
