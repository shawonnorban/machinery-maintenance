<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fabric Booking → Style-Wise Yarn Spec Confirmation (AnnotechERP 06/11): per
 * style and production category, the fabric a garment item's parts are knit
 * from, for which garment colours (or all) and in which fabric colours —
 * pending until confirmed, and released again before it is corrected. One
 * save writes a row for each production category picked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_yarn_specs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('style_id')->constrained('merch_styles')->cascadeOnDelete();
            $table->string('prod_category', 12);
            $table->foreignUlid('fabric_type_id')->constrained('fabric_types')->restrictOnDelete();
            $table->string('gmt_item', 96);
            $table->json('part_ids');
            $table->boolean('all_colours')->default(false);
            $table->json('gmt_colour_ids');
            $table->json('fab_colour_ids');
            $table->string('status', 12)->default('PENDING');
            $table->timestamp('confirmed_at')->nullable();
            $table->ulid('confirmed_by')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'style_id', 'prod_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_yarn_specs');
    }
};
