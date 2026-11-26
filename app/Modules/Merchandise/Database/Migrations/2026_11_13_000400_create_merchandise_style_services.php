<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item/Combo/Model → Services (AnnotechERP 06/05): for each colour a style is
 * made in, its AOP, yarn-dyed, print, embroidery and garment-wash type; and
 * the style's process details — dyeing, print, embroidery, wash … — each with
 * its type, supplier, remarks and a picture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_style_colours', function (Blueprint $table): void {
            $table->string('aop_type', 24)->nullable();
            $table->string('yd_type', 24)->nullable();
            $table->string('print_type', 24)->nullable();
            $table->string('embroidery_type', 24)->nullable();
            $table->string('wash_type', 24)->nullable();
        });

        Schema::create('merch_style_processes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->string('process', 16);
            $table->string('type', 128)->nullable();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('remarks', 500)->nullable();
            $table->ulid('image_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'style_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_processes');
        Schema::table('merch_style_colours', function (Blueprint $table): void {
            $table->dropColumn(['aop_type', 'yd_type', 'print_type', 'embroidery_type', 'wash_type']);
        });
    }
};
