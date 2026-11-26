<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costing & Budget → Fabric Process Rate (AnnotechERP 06/09): the rate chart
 * a fabric booking and a budget are costed from — knitting, yarn dyeing, AOP,
 * dyeing and dyeing-finishing rates by fibre combination, count range, fabric
 * type and colour type, with the production rate and the process loss
 * allowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_fab_process_rates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedInteger('rate_no');
            $table->string('category', 20);
            $table->string('fiber_template', 16)->nullable();
            $table->string('count_range', 64)->nullable();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->foreignUlid('colour_group_id')->nullable()->constrained('dye_colour_groups')->nullOnDelete();
            $table->boolean('is_slub')->default(false);
            $table->string('description', 500);
            $table->decimal('rate', 14, 4);
            $table->string('currency', 3)->default('BDT');
            $table->string('per', 16)->default('Kg');
            $table->decimal('prod_rate', 14, 4);
            $table->decimal('process_loss', 5, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'rate_no']);
            $table->index(['company_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_fab_process_rates');
    }
};
