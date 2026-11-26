<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the finished
 * fabric opening balance — opening stock of finished fabric carried into the
 * period, by receiving store (and, if any, the order it is for), one roll
 * batch per line at its cost, the USD rate kept with the document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_ffab_obs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ob_no', 32);
            $table->ulid('store_id');
            $table->date('ob_date');
            $table->ulid('order_id')->nullable();
            $table->string('currency', 8)->default('USD');
            $table->decimal('conv_rate', 14, 6)->default(1);
            $table->decimal('usd_bdt', 12, 4)->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'ob_no']);
            $table->index(['company_id', 'ob_date']);
        });

        Schema::create('merch_ffab_ob_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('ob_id')->constrained('merch_ffab_obs')->cascadeOnDelete();
            $table->string('category', 60)->nullable();
            $table->string('description', 200);
            $table->string('fabric_item', 120);
            $table->string('colour', 80)->nullable();
            $table->ulid('fabric_type_id');
            $table->string('fabric_group', 12);
            $table->ulid('composition_id')->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('dia', 8, 2)->nullable();
            $table->string('dia_uom', 8)->nullable();
            $table->string('dia_type', 8)->nullable();
            $table->string('batch_no', 60)->nullable();
            $table->unsignedInteger('rolls')->nullable();
            $table->string('uom', 8);
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('cost_bdt', 16, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'ob_id']);
            $table->index(['company_id', 'batch_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_ffab_ob_lines');
        Schema::dropIfExists('merch_ffab_obs');
    }
};
