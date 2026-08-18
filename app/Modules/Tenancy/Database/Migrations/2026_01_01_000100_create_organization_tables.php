<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization hierarchy: ERD Section 2.
 *
 * Platform > Organization > Company > Business Unit > Factory > Building >
 * Floor > Department > Section > Production Line > Workstation.
 *
 * Every level below Company carries company_id so tenant scoping never has to
 * traverse a join to establish ownership (ERD rule 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()
                ->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->string('legal_name')->nullable();
            $table->char('base_currency', 3)->default('BDT');
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('default_locale', 8)->default('en');
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code', 'deleted_at']);
            $table->index('status');
        });

        Schema::create('business_units', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('factories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUlid('business_unit_id')->nullable()
                ->constrained('business_units')->nullOnDelete();
            $table->string('name');
            // Used as the {FACTORY} segment in document numbers (Data Dictionary 6).
            $table->string('code', 5);
            $table->text('address')->nullable();
            $table->string('timezone', 64)->default('Asia/Dhaka');
            $table->string('status', 32)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('buildings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->timestamps();

            $table->unique(['company_id', 'factory_id', 'code']);
        });

        Schema::create('floors', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUlid('building_id')->constrained('buildings')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->timestamps();

            $table->unique(['company_id', 'building_id', 'code']);
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUlid('factory_id')->constrained('factories')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->timestamps();

            $table->unique(['company_id', 'factory_id', 'code']);
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUlid('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->timestamps();

            $table->unique(['company_id', 'department_id', 'code']);
        });

        Schema::create('production_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUlid('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUlid('section_id')->nullable()
                ->constrained('sections')->nullOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->timestamps();

            $table->unique(['company_id', 'department_id', 'code']);
        });

        Schema::create('workstations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUlid('production_line_id')->constrained('production_lines')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->timestamps();

            $table->unique(['company_id', 'production_line_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workstations');
        Schema::dropIfExists('production_lines');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('floors');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('factories');
        Schema::dropIfExists('business_units');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('organizations');
    }
};
