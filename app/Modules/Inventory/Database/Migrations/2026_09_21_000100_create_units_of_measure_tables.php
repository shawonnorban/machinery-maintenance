<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Units of measure as a maintained list rather than the hardcoded
 * `SparePart::UNITS` enum (Inventory spec, Master Data §7).
 *
 * The enum stays for the existing `unit` column; this table is what a
 * purchase can be placed in when it differs from the stock unit — fabric
 * bought by the yard and stocked by the metre — with `uom_conversions`
 * holding the factor between the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Null company_id = platform-seeded, visible to every tenant.
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'units_of_measure_code_unique');
        });

        Schema::create('uom_conversions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('from_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->foreignUlid('to_uom_id')->constrained('units_of_measure')->cascadeOnDelete();
            // 1 from-unit = factor to-units.
            $table->decimal('factor', 18, 6);
            $table->timestamps();

            $table->unique(['company_id', 'from_uom_id', 'to_uom_id'], 'uom_conversions_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uom_conversions');
        Schema::dropIfExists('units_of_measure');
    }
};
