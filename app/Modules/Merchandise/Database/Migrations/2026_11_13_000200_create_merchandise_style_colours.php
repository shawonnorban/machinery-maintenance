<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item/Combo/Model → Colour Mapping (AnnotechERP 06/05): the colours a style is
 * made in — each from Colour Master, with its type, whether it is a garment
 * colour, colour group, dye method and composition, the buyer's references
 * (colour ref, shade, Pantone, buyer colour name), whether a swatch came, and
 * a picture. Services, fabric mapping and bookings are recorded against them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_style_colours', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->foreignUlid('colour_id')->constrained('colours')->restrictOnDelete();
            $table->string('colour_type', 16)->nullable();
            $table->boolean('is_gmt_colour')->default(false);
            $table->foreignUlid('colour_group_id')->nullable()->constrained('dye_colour_groups')->nullOnDelete();
            $table->foreignUlid('dye_method_id')->nullable()->constrained('dye_methods')->nullOnDelete();
            $table->foreignUlid('fiber_composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->string('colour_ref', 64)->nullable();
            $table->string('shade_no', 32)->nullable();
            $table->boolean('swatch')->default(false);
            $table->string('pantone_no', 32)->nullable();
            $table->string('buyer_colour_name', 128)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->ulid('image_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['style_id', 'colour_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_colours');
    }
};
