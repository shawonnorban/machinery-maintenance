<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Dip & Sample → Print Design Sheet (AnnotechERP 06/10): who asks for a
 * print design and strike-off, of whom and on what date, and the list of
 * styles, orders and instructions it is asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_print_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 32);
            $table->ulid('buyer_id');
            $table->ulid('buyer_office_id');
            $table->date('requisition_date');
            $table->ulid('program_by');
            $table->string('request_to', 160);
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
        });

        Schema::create('merch_print_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('merch_print_programs')->cascadeOnDelete();
            $table->ulid('style_id')->nullable();
            $table->ulid('order_id')->nullable();
            $table->ulid('sample_type_id');
            $table->string('material_description', 255);
            $table->string('nature_of_work', 160)->nullable();
            $table->date('shipment_date');
            $table->string('item', 96)->nullable();
            $table->string('old_style_no', 96)->nullable();
            $table->string('print_type', 96)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('print_color', 96)->nullable();
            $table->decimal('req_qty', 12, 2)->nullable();
            $table->string('unit', 16)->nullable();
            $table->string('priority', 8)->nullable();
            $table->text('instruction')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_print_lines');
        Schema::dropIfExists('merch_print_programs');
    }
};
