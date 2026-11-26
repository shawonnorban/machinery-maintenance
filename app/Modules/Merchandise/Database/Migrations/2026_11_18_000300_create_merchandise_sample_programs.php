<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Dip & Sample → Sample Programme (AnnotechERP 06/10): proto, fit, PP and
 * size-set samples planned against a job / work style, and the lines that
 * ask the sample room for them — type, item, colour, size and quantity.
 * Sample Production pulls lines into a batch (`batch_no`); a line in a batch
 * belongs to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_sample_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 32);
            $table->date('program_date');
            $table->string('tran_ref', 64)->nullable();
            $table->ulid('buyer_office_id')->nullable();
            $table->ulid('order_id')->nullable();
            $table->string('work_style_no', 96)->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->text('remarks')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
        });

        Schema::create('merch_sample_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('merch_sample_programs')->cascadeOnDelete();
            $table->ulid('sample_type_id');
            $table->string('item', 96)->nullable();
            $table->string('colour', 96)->nullable();
            $table->string('size', 32)->nullable();
            $table->decimal('req_qty', 12, 2);
            $table->string('unit', 16)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 16)->default('PLANNED');
            $table->string('batch_no', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_sample_lines');
        Schema::dropIfExists('merch_sample_programs');
    }
};
