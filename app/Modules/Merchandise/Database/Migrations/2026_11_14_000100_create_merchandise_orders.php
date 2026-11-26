<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Style & Order → Order Register (AnnotechERP 06/07): the order book. An order
 * is numbered <buyer code>-<year>-<000001>, names its job, style, buyer and
 * unit, its dates and ship country, its quantity, unit price and currency, and
 * a status. Its colour × size breakdown, when given, decides its quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('order_no', 32);
            $table->string('job_no', 64);
            $table->foreignUlid('style_id')->nullable()->constrained('merch_styles')->nullOnDelete();
            $table->string('style_no', 96);
            $table->string('work_style_no', 96)->nullable();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->foreignUlid('buyer_office_id')->nullable()->constrained('buyer_offices')->nullOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->string('order_type', 16)->default('BULK_NEW');
            $table->string('item', 128)->nullable();
            $table->string('gmt_type', 128)->nullable();
            $table->date('order_date')->nullable();
            $table->date('conf_date')->nullable();
            $table->date('ship_date')->nullable();
            $table->foreignUlid('ship_country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->unsignedInteger('total_qty');
            $table->string('unit', 8)->default('PCS');
            $table->decimal('unit_price', 14, 4)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('status', 16)->default('CONFIRMED');
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'order_no']);
            $table->index(['company_id', 'buyer_id']);
            $table->index(['company_id', 'style_id']);
        });

        Schema::create('merch_order_sizes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('merch_orders')->cascadeOnDelete();
            $table->string('colour', 64);
            $table->string('size', 32);
            $table->unsignedInteger('qty');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['order_id', 'colour', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_order_sizes');
        Schema::dropIfExists('merch_orders');
    }
};
