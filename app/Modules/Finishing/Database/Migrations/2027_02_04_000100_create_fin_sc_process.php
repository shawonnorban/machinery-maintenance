<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Finishing Line → S/C Process (AnnotechERP 18/03): fabric sent off the
 * finishing floor to a sub-contract processor on a challan (one line per
 * shade), the finished fabric taken back against the challan lines it
 * answers (with the short weight the party disputes), and the processor's
 * bill, line by process, against the challans it charges for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_sc_challans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('challan_date');
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 10)->default('DRAFT'); // DRAFT | POSTED | CANCELLED
            $table->timestamp('posted_at')->nullable();
            $table->foreignUlid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no'], 'fin_sc_challans_no_unique');
        });

        Schema::create('fin_sc_challan_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('challan_id')->constrained('fin_sc_challans')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->nullable()->constrained('fin_batch_receipts')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('style', 120)->nullable();
            $table->string('colour', 120)->nullable();
            $table->foreignUlid('process_id')->constrained('fin_process_types')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->string('dia_type', 8)->nullable(); // OPEN | TUBE
            $table->decimal('qty_kg', 14, 3);
            $table->unsignedInteger('rolls')->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fin_sc_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mrr_no', 24);
            $table->date('receive_date');
            $table->foreignUlid('challan_id')->nullable()->constrained('fin_sc_challans')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 10)->default('DRAFT'); // DRAFT | POSTED
            $table->timestamp('posted_at')->nullable();
            $table->foreignUlid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mrr_no'], 'fin_sc_receipts_no_unique');
        });

        Schema::create('fin_sc_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('fin_sc_receipts')->cascadeOnDelete();
            $table->foreignUlid('challan_line_id')->nullable()->constrained('fin_sc_challan_lines')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('recv_kg', 14, 3);
            $table->unsignedInteger('rolls')->nullable();
            $table->decimal('short_kg', 14, 3)->default(0);
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fin_sc_bills', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('bill_no', 40);
            $table->date('bill_date');
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('ref_bill_no', 40)->nullable();
            $table->string('rev_no', 16)->nullable();
            $table->string('rev_description')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 10)->default('DRAFT'); // DRAFT | SUBMITTED | COMPLETED | CANCELLED
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'supplier_id', 'bill_no'], 'fin_sc_bills_party_no_unique');
        });

        Schema::create('fin_sc_bill_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bill_id')->constrained('fin_sc_bills')->cascadeOnDelete();
            $table->foreignUlid('challan_id')->constrained('fin_sc_challans')->restrictOnDelete();
            $table->foreignUlid('process_id')->constrained('fin_process_types')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('qty_kg', 14, 3);
            $table->decimal('rate', 12, 2);
            $table->decimal('amount', 14, 2);
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['fin_sc_bill_lines', 'fin_sc_bills', 'fin_sc_receipt_lines', 'fin_sc_receipts', 'fin_sc_challan_lines', 'fin_sc_challans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
