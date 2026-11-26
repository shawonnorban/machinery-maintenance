<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costing & Budget → Print & Embroidery Rate Setup (AnnotechERP 06/09): a
 * print or embroidery rate against a style or an order, for a buyer and a
 * supplier — what the work is, its rate, currency and unit. A rate is costed
 * against once it is confirmed, and is no longer rewritten after that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_embellishment_rates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('type', 16);
            $table->foreignUlid('style_id')->nullable()->constrained('merch_styles')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('description', 1000);
            $table->decimal('rate', 14, 4);
            $table->string('currency', 3)->default('BDT');
            $table->string('per', 16)->default('Pcs');
            $table->boolean('confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->ulid('confirmed_by')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'buyer_id', 'confirmed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_embellishment_rates');
    }
};
