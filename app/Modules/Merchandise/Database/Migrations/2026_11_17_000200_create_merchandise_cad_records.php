<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costing & Budget → CAD Consumption Register (AnnotechERP 06/09): marker
 * consumption imported from CAD — a record per job / work style, for a buyer
 * account, style or order, in a category and a status — and its consumption
 * per size and garment part.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_cad_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_office_id')->nullable()->constrained('buyer_offices')->nullOnDelete();
            $table->foreignUlid('style_id')->nullable()->constrained('merch_styles')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('category', 16)->nullable();
            $table->string('job_no', 96);
            $table->date('record_date');
            $table->string('status', 16)->default('DRAFT');
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'record_date']);
        });

        Schema::create('merch_cad_sizes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('cad_id')->constrained('merch_cad_records')->cascadeOnDelete();
            $table->string('size', 32);
            $table->foreignUlid('garment_part_id')->nullable()->constrained('garment_parts')->nullOnDelete();
            $table->decimal('consumption', 12, 4);
            $table->string('uom', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_cad_sizes');
        Schema::dropIfExists('merch_cad_records');
    }
};
