<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What running the platform costs.
 *
 * The one table in this schema with no company_id, and deliberately so: a
 * server bill belongs to the business that runs the product, not to any
 * customer of it. Adding a company_id "for consistency" would put the
 * platform's own costs inside somebody's tenancy, where the tenant scope would
 * then happily show them a row they have nothing to do with.
 *
 * Income has been recorded since the billing module was written —
 * subscription_invoices and subscription_payments — so this is the missing
 * half of "what did the business take and what did it spend".
 *
 * Note what is NOT here: salaries, wages, and anything else naming a person's
 * pay. Payroll is out of scope for this product by an explicit decision, and
 * "it is only an expense category" is exactly how payroll data gets into a
 * schema that promised not to hold any.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_expenses', function (Blueprint $table): void {
            $table->ulid('id')->primary();

            $table->date('spent_on');
            $table->string('category', 32);
            $table->string('description', 255);

            // The same shape as every other money column in the product:
            // DECIMAL(18,4) with the currency beside it, never a float
            // (ADR-063).
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3);

            $table->string('vendor', 255)->nullable();
            $table->string('reference', 64)->nullable();

            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('spent_on');
            $table->index(['category', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_expenses');
    }
};
