<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance & Ledger → Cheque Management (AnnotechERP 23/05):
 *
 * - `acc_cheque_issues` + `acc_cheque_issue_lines`: a cheque taken off a
 *   registered book (one leaf, once) and made out, with what it settles by
 *   control head beneath it. Printed once; a printed cheque is never removed,
 *   only cancelled.
 * - `acc_cheque_cancellations`: a leaf taken out of circulation — unused, or
 *   an issued cheque voided — the leaf kept, deactivated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_cheque_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('issue_ref', 24);
            $table->foreignUlid('bank_account_id')->constrained('acc_bank_accounts')->restrictOnDelete();
            $table->foreignUlid('cheque_leaf_id')->unique()->constrained('acc_cheque_leaves')->restrictOnDelete();
            $table->date('issue_date');
            $table->date('place_date')->nullable();
            $table->string('issue_type', 20); // MONEY_REQUISITION | BILL_PAYMENT | SUPPLIER_PAYMENT | ADVANCE | SALARY | UTILITY | OTHERS
            $table->foreignUlid('issue_to_head_id')->constrained('acc_accounts')->restrictOnDelete();
            $table->string('pay_to', 160);
            $table->string('issue_for', 60)->nullable();
            $table->decimal('charges', 16, 4)->default(0);
            $table->boolean('delivered')->default(false);
            $table->decimal('amount', 16, 4);
            $table->boolean('acc_payee')->default(false);
            $table->foreignUlid('fiscal_year_id')->nullable()->constrained('acc_fiscal_years')->nullOnDelete();
            $table->text('purpose')->nullable();
            $table->text('narration')->nullable();
            $table->foreignUlid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->boolean('cancelled')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'issue_ref']);
        });

        Schema::create('acc_cheque_issue_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('cheque_issue_id')->constrained('acc_cheque_issues')->cascadeOnDelete();
            $table->foreignUlid('control_head_id')->constrained('acc_accounts')->restrictOnDelete();
            $table->string('issue_for', 120)->nullable();
            $table->string('issue_ref', 48)->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->decimal('req_amt', 16, 4)->nullable();
            $table->decimal('amount', 16, 4);
            $table->string('remarks', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'issue_ref']);
        });

        Schema::create('acc_cheque_cancellations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('cheque_leaf_id')->unique()->constrained('acc_cheque_leaves')->restrictOnDelete();
            $table->foreignUlid('cheque_issue_id')->nullable()->constrained('acc_cheque_issues')->nullOnDelete();
            $table->string('issue_ref', 48)->nullable();
            $table->text('remarks');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['acc_cheque_cancellations', 'acc_cheque_issue_lines', 'acc_cheque_issues'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
