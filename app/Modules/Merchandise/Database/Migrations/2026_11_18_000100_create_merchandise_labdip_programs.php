<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lab Dip & Sample → Lab Dip Programme (AnnotechERP 06/10): a lab dip
 * request raised for a buyer to a lab — an internal section or a nominated
 * house — and the shades on it, each with its colour, Pantone, fabric, light
 * source and target date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_labdip_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 32);
            $table->ulid('buyer_id');
            $table->ulid('buyer_office_id');
            $table->date('request_date');
            $table->ulid('request_section_id')->nullable();
            $table->ulid('request_supplier_id')->nullable();
            $table->ulid('request_by');
            $table->ulid('attn_user_id')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
            $table->index(['company_id', 'buyer_id']);
        });

        Schema::create('merch_labdip_shades', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('merch_labdip_programs')->cascadeOnDelete();
            $table->ulid('style_id')->nullable();
            $table->ulid('order_id')->nullable();
            $table->string('colour', 96);
            $table->string('pantone', 48)->nullable();
            $table->string('fabric', 160)->nullable();
            $table->string('light_source', 16)->nullable();
            $table->date('target_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_labdip_shades');
        Schema::dropIfExists('merch_labdip_programs');
    }
};
