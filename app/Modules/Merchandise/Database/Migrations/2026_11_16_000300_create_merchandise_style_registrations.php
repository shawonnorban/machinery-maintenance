<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Style & Order → Style Registration Entry (AnnotechERP 06/07): a style is
 * registered from an approved development style through a request
 * (SRQ-2026-000001) that carries its style code (ST-2026-00001) and the
 * profile the development style lacks — brand, product, category, type,
 * gender, season year, collection, department, division. Approved, the
 * request issues the registration number and its version becomes the active
 * style; a registered style changes through a change request (the next
 * version).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_style_registrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('request_no', 24);
            $table->string('type', 16)->default('REGISTRATION');
            $table->unsignedInteger('version')->default(1);
            $table->foreignUlid('style_id')->constrained('merch_styles')->restrictOnDelete();
            $table->string('style_code', 24);
            $table->string('registration_no', 24)->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->boolean('active')->default(false);
            $table->string('brand', 96)->nullable();
            $table->string('product', 96)->nullable();
            $table->string('product_category', 96)->nullable();
            $table->string('product_type', 96)->nullable();
            $table->string('gender', 12)->nullable();
            $table->unsignedSmallInteger('season_year')->nullable();
            $table->string('collection', 96)->nullable();
            $table->string('department', 96)->nullable();
            $table->string('division', 96)->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'request_no']);
            $table->index(['company_id', 'style_code']);
            $table->index(['company_id', 'style_id']);
        });

        Schema::create('merch_style_registration_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('registration_id')->constrained('merch_style_registrations')->cascadeOnDelete();
            $table->string('action', 16);
            $table->text('note')->nullable();
            $table->ulid('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_style_registration_events');
        Schema::dropIfExists('merch_style_registrations');
    }
};
