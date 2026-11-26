<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trade & Export → CM Payment Receive (AnnotechERP 22/04):
 *
 * - `tx_cm_receipts`: the money received against a final bill of exchange
 *   (CMR-2026-000001), with its rate and DFC margin.
 * - `tx_bills_of_exchange` gains the bill's submit and payment dates at the
 *   bank (Bill Adjustment's "Manage Bill Submit and Payment Date") and a
 *   margin type for the advances raised from Margin Closing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tx_cm_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('receipt_no', 24);
            $table->foreignUlid('boe_id')->constrained('tx_bills_of_exchange')->restrictOnDelete();
            $table->string('bank_bill_type', 4);
            $table->decimal('amount', 16, 4);
            $table->date('receive_date');
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->decimal('dfc_margin', 16, 4)->default(0);
            $table->boolean('final')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'receipt_no']);
        });

        Schema::table('tx_bills_of_exchange', function (Blueprint $table): void {
            $table->date('bank_submit_date')->nullable()->after('ref_date');
            $table->date('payment_recv_date')->nullable()->after('bank_submit_date');
            $table->string('margin_type', 12)->nullable()->after('order_id'); // MARGIN | COMMISSION | INTEREST | BANK_CHARGE
        });
    }

    public function down(): void
    {
        Schema::table('tx_bills_of_exchange', fn (Blueprint $table) => $table->dropColumn(['bank_submit_date', 'payment_recv_date', 'margin_type']));
        Schema::dropIfExists('tx_cm_receipts');
    }
};
