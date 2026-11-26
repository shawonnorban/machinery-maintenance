<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Buyer Gateway → Pricing & Billing (AnnotechERP 25/03): requests for
 * quotation before they become an inquiry, the agreed prices a buyer sees,
 * the invoices published to the buyer, and each buyer's billed / received.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bg_rfqs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference', 40);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('ws_garment_items')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('target_price', 10, 2)->nullable();
            $table->date('received_on')->nullable();
            $table->date('respond_by')->nullable();
            $table->string('state', 16)->default('NEW'); // NEW | UNDER_COSTING | QUOTED | DECLINED | CONVERTED
            $table->timestamps();
            $table->unique(['company_id', 'reference'], 'bg_rfqs_reference_unique');
        });

        Schema::create('bg_price_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('product', 160);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('season', 16)->nullable(); // "Winter 26"
            $table->decimal('price', 10, 2)->default(0);
            $table->string('incoterm', 4)->nullable(); // FOB | CIF | DDP | EXW
            $table->unsignedInteger('min_qty')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });

        Schema::create('bg_invoices', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('invoice_no', 40);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('order_no', 60)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->string('state', 12)->default('PUBLISHED'); // PUBLISHED | PAID | OVERDUE | UNPUBLISHED
            $table->timestamps();
            $table->unique(['company_id', 'invoice_no'], 'bg_invoices_number_unique');
        });

        Schema::create('bg_payment_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->decimal('billed', 14, 2)->default(0);
            $table->decimal('received', 14, 2)->default(0);
            $table->string('payment_terms', 12)->nullable(); // LC_SIGHT | LC_60 | TT_30 | TT_60 | OPEN_90
            $table->date('next_due')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'buyer_id'], 'bg_payment_lines_buyer_unique');
        });
    }

    public function down(): void
    {
        foreach (['bg_payment_lines', 'bg_invoices', 'bg_price_lines', 'bg_rfqs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
