<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company Management (AnnotechERP 01/*): the Brickland screens over the
 * organisation tables that already exist.
 *
 * The tables are extended rather than replaced because machinery maintenance
 * (asset locations, breakdowns, work orders) already hangs off them. Status is
 * a column rather than a delete: a building that still has floors, lines or
 * machines on it is set Inactive, never removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('website')->nullable();
            $table->string('trade_license_no', 64)->nullable();
            $table->string('tin', 32)->nullable();
            $table->string('bin', 32)->nullable();
            $table->string('registration_no', 64)->nullable();
            $table->date('registration_date')->nullable();
        });

        Schema::table('factories', function (Blueprint $table): void {
            $table->string('contact', 64)->nullable();
            $table->date('established_date')->nullable();
            $table->decimal('area_sqft', 14, 2)->nullable();
            $table->text('remarks')->nullable();
        });

        Schema::table('buildings', function (Blueprint $table): void {
            // Declared; "Floors Registered" is counted from the floors table.
            $table->unsignedSmallInteger('number_of_floors')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->text('remarks')->nullable();
        });

        Schema::table('floors', function (Blueprint $table): void {
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 16)->default('ACTIVE');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->string('department_type', 24)->nullable();
            $table->string('status', 16)->default('ACTIVE');
        });

        Schema::table('sections', function (Blueprint $table): void {
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->restrictOnDelete();
            $table->string('status', 16)->default('ACTIVE');
        });

        Schema::table('production_lines', function (Blueprint $table): void {
            // Nullable in the table so existing lines survive the migration; the
            // screen requires it for every line saved from now on.
            $table->string('line_type', 24)->nullable();
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->restrictOnDelete();
            $table->unsignedInteger('manpower')->nullable();
            $table->decimal('capacity_per_day', 12, 2)->nullable();
            $table->decimal('target_per_day', 12, 2)->nullable();
            $table->string('status', 16)->default('ACTIVE');
        });

        Schema::table('workstations', function (Blueprint $table): void {
            $table->string('status', 16)->default('ACTIVE');
        });

        Schema::create('grades', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->unsignedInteger('sort_order')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('designations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->foreignUlid('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            // WORKER / STAFF / OFFICER / MANAGEMENT until HR & Payroll's own
            // Employee Category master arrives and this becomes a foreign key.
            $table->string('employee_category', 24)->nullable();
            $table->foreignUlid('grade_id')->nullable()->constrained('grades')->restrictOnDelete();
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('company_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('document_type', 32);
            $table->string('title');
            $table->string('document_no', 64)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issued_by')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();

            $table->index(['company_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('grades');

        Schema::table('workstations', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('production_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('floor_id');
            $table->dropColumn(['line_type', 'manpower', 'capacity_per_day', 'target_per_day', 'status']);
        });
        Schema::table('sections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('floor_id');
            $table->dropColumn('status');
        });
        Schema::table('departments', fn (Blueprint $table) => $table->dropColumn(['department_type', 'status']));
        Schema::table('floors', fn (Blueprint $table) => $table->dropColumn(['capacity', 'status']));
        Schema::table('buildings', fn (Blueprint $table) => $table->dropColumn(['number_of_floors', 'status', 'remarks']));
        Schema::table('factories', fn (Blueprint $table) => $table->dropColumn(['contact', 'established_date', 'area_sqft', 'remarks']));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn([
            'website', 'trade_license_no', 'tin', 'bin', 'registration_no', 'registration_date',
        ]));
    }
};
