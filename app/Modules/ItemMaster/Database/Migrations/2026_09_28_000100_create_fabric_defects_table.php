<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The defect catalogue an inspector picks from (Inventory spec §4.4):
 * single source of truth, Knit and Woven kept as separate lists rather than
 * one list with irrelevant entries for the fabric in hand. Not tenant-scoped
 * — a platform-seeded default list every company starts from, matching
 * `units_of_measure`'s own nullable-company_id pattern — but a company may
 * add its own on top via the ordinary Master Data screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabric_defects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('fabric_family', 8);
            $table->string('code', 48);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'fabric_family', 'code'], 'fabric_defects_unique');
            $table->index(['fabric_family', 'active'], 'fabric_defects_family_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_defects');
    }
};
