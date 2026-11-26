<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance & Ledger → Voucher Entry (AnnotechERP 23/02):
 *
 * - `acc_vouchers` + `acc_voucher_lines`: a voucher and its debit / credit
 *   lines. A draft carries only its reference (VR-2026-000001); approval
 *   commits it, numbers it by type (JV-2026-000001) and sends it to posting
 *   (UNPOSTED → POSTED). Voucher Entry (23/03) raises into the same table.
 * - `acc_mrr_bills` + `acc_mrr_bill_lines`: a supplier bill drawn from one or
 *   more material receive reports of one kind, each MRR on one bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_vouchers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('voucher_no', 24)->nullable();
            $table->string('voucher_ref_no', 24);
            $table->date('voucher_date');
            $table->string('voucher_type', 4); // BPV | BRV | CPV | CRV | CV | BV | JV
            $table->string('trx_source', 12)->nullable(); // FINANCE | COMMERCIAL | STORE | HR | SYSTEM
            $table->string('bill_no', 48)->nullable();
            $table->string('voucher_for_type', 10)->default('GENERAL'); // GENERAL | SUPPLIER | BUYER | EMPLOYEE | PARTY
            $table->string('voucher_for', 160)->nullable();
            $table->text('narration')->nullable();
            $table->string('action_status', 20)->default('CREATE_VOUCHER'); // CREATE_VOUCHER | SUBMIT_FOR_APPROVAL | APPROVED | REJECTED | ON_HOLD
            $table->string('posting_status', 10)->default('DRAFT'); // DRAFT | UNPOSTED | POSTED | CANCELLED
            $table->decimal('total', 16, 4)->default(0);
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'voucher_no']);
            $table->unique(['company_id', 'voucher_ref_no']);
            $table->index(['company_id', 'posting_status', 'voucher_date']);
        });

        Schema::create('acc_voucher_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('voucher_id')->constrained('acc_vouchers')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('acc_accounts')->restrictOnDelete();
            $table->foreignUlid('cost_center_id')->nullable()->constrained('acc_cost_centers')->nullOnDelete();
            $table->string('bill_no', 48)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('ref_no', 48)->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->decimal('rate', 14, 6)->default(1);
            $table->decimal('value', 16, 4)->nullable();
            $table->decimal('debit', 16, 4)->default(0);
            $table->decimal('credit', 16, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('acc_mrr_bills', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('bill_no', 24);
            $table->date('bill_date');
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('mrr_type', 8); // GENERAL | YARN | DYES | TRIMS | OIL | NEEDLE
            $table->foreignUlid('prepared_by')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignUlid('maker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('credit_account_id')->constrained('acc_accounts')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->boolean('fixed_asset')->default(false);
            $table->boolean('final')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'bill_no']);
        });

        Schema::create('acc_mrr_bill_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bill_id')->constrained('acc_mrr_bills')->cascadeOnDelete();
            $table->foreignUlid('debit_account_id')->constrained('acc_accounts')->restrictOnDelete();
            $table->string('mrr_kind', 8);
            $table->ulid('mrr_id');
            $table->string('mrr_no', 32);
            $table->date('mrr_date');
            $table->string('po_wo', 48)->nullable();
            $table->decimal('rate', 16, 4)->default(0);
            $table->string('uom', 24)->nullable();
            $table->decimal('total_qty', 16, 4)->default(0);
            $table->decimal('total_amount', 16, 4)->default(0);
            $table->decimal('other_cost', 16, 4)->default(0);
            $table->decimal('vds', 16, 4)->default(0);
            $table->decimal('tds', 16, 4)->default(0);
            $table->decimal('net_amount', 16, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['mrr_kind', 'mrr_id']);
        });
    }

    public function down(): void
    {
        foreach (['acc_mrr_bill_lines', 'acc_mrr_bills', 'acc_voucher_lines', 'acc_vouchers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
