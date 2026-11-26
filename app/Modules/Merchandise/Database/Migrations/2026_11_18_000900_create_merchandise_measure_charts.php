<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Dip & Sample → Collar & Cuff Measurement Sheet (AnnotechERP 06/10): a
 * measurement chart per style, sub-style, item (collar, cuff) and model, and
 * its points of measure per size — value, tolerance and unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_measure_charts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('style_id');
            $table->ulid('buyer_office_id')->nullable();
            $table->string('sub_style', 64)->nullable();
            $table->string('item_name', 96);
            $table->string('model_no', 64)->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'style_id']);
        });

        Schema::create('merch_measure_points', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('chart_id')->constrained('merch_measure_charts')->cascadeOnDelete();
            $table->string('size', 32);
            $table->string('point', 160);
            $table->decimal('value', 10, 2);
            $table->decimal('tolerance', 10, 2)->nullable();
            $table->string('uom', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_measure_points');
        Schema::dropIfExists('merch_measure_charts');
    }
};
