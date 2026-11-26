<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fabric Requirement → Reports (AnnotechERP 13/03), as Brickland keeps them:
 *
 * - `fp_reports`: the module's report catalogue — data, not a hard-coded list,
 *   so a report can be retired without a deployment.
 * - `fp_report_runs`: a report run with the unit, buyer and week it covered,
 *   and how many rows it gave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fp_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 120);
            // PLAN | CAPACITY | SCHEDULE | CONFIGURATION
            $table->string('category', 16);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('fp_report_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('report_id')->constrained('fp_reports')->restrictOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->date('week_start')->nullable();
            $table->boolean('export_excel')->default(false);
            $table->unsignedInteger('rows')->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fp_report_runs');
        Schema::dropIfExists('fp_reports');
    }
};
