<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Finance & Ledger → Master Data (AnnotechERP 23/01): payment modes, who may
 * make and check each voucher type, the chart of accounts, cost centres and
 * their groups, the company's bank accounts and their cheque books, fiscal
 * years, account type classifications and statutory ledger rules. The bank
 * register itself is Trade & Export's (`tx_banks`) — Brickland keeps one, and
 * this screen adds whether a bank is the treasury's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tx_banks', function (Blueprint $table): void {
            $table->boolean('is_treasury')->default(false)->after('is_local');
        });

        Schema::create('acc_payment_modes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name', 16)->nullable();
            $table->json('voucher_types');
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'acc_payment_modes_unique');
        });

        // Maker-Checker Setup: who may raise and who must approve each voucher type, as separate grants.
        Schema::create('acc_voucher_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('voucher_type', 4);
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('can_make')->default(false);
            $table->boolean('can_check')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'voucher_type', 'user_id'], 'acc_voucher_grants_unique');
        });

        Schema::create('acc_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name');
            $table->ulid('parent_id')->nullable();
            $table->string('type', 16); // ASSET | LIABILITY | EQUITY | REVENUE | EXPENSE
            $table->boolean('postable')->default(true);
            $table->string('voucher_ref_type', 16)->nullable(); // BUYER | SUPPLIER | EMPLOYEE | BANK | BANK_ACCOUNT
            $table->ulid('voucher_ref_id')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'acc_accounts_code_unique');
        });
        Schema::table('acc_accounts', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('acc_accounts')->restrictOnDelete();
        });

        Schema::create('acc_cost_center_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'acc_cost_center_groups_unique');
        });

        Schema::create('acc_cost_centers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 8);
            $table->string('name');
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->foreignUlid('group_id')->nullable()->constrained('acc_cost_center_groups')->nullOnDelete();
            $table->string('dept_section')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'acc_cost_centers_code_unique');
        });

        // The company's own accounts at a bank branch, which cheque books are issued against.
        Schema::create('acc_bank_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bank_id')->constrained('tx_banks')->restrictOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained('tx_bank_branches')->restrictOnDelete();
            $table->string('account_no', 32);
            $table->string('account_name');
            $table->string('account_type', 16); // CURRENT | SAVINGS | STD | SND | FC | CC | OD
            $table->string('currency', 3);
            $table->foreignUlid('ledger_id')->nullable()->constrained('acc_accounts')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'account_no'], 'acc_bank_accounts_no_unique');
        });

        Schema::create('acc_cheque_books', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bank_account_id')->constrained('acc_bank_accounts')->restrictOnDelete();
            $table->string('book_no', 32);
            $table->unsignedInteger('required_leaf');
            $table->string('prefix', 12);
            $table->unsignedBigInteger('no_from');
            $table->unsignedBigInteger('no_to');
            $table->date('requisition_date');
            $table->date('est_receive_date')->nullable();
            $table->date('receive_date');
            $table->text('remarks')->nullable();
            $table->boolean('finalized')->default(false);
            $table->timestamps();
            $table->unique(['bank_account_id', 'book_no'], 'acc_cheque_books_unique');
        });

        Schema::create('acc_cheque_leaves', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('book_id')->constrained('acc_cheque_books')->cascadeOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('cheque_no', 40);
            $table->string('status', 12)->default('UNUSED'); // UNUSED | ISSUED | CANCELLED
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['book_id', 'number'], 'acc_cheque_leaves_unique');
        });

        Schema::create('acc_fiscal_years', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 32);
            $table->date('fy_start');
            $table->date('fy_end');
            $table->date('cal_start');
            $table->date('cal_end');
            $table->boolean('closed')->default(false);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'acc_fiscal_years_unique');
        });

        // Account Type Configuration: one classification per account per layer.
        Schema::create('acc_account_classes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('account_type', 16); // CASH | BANK | RECEIVABLE | PAYABLE | INVENTORY | GENERAL
            $table->string('layer', 16); // MAIN_CLASS | SUB_CLASS | CONTROL | LEDGER
            $table->foreignUlid('account_id')->constrained('acc_accounts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['account_id', 'layer'], 'acc_account_classes_unique');
        });

        // Statutory Ledger Rules: one rule per ledger head.
        Schema::create('acc_compliance_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('acc_accounts')->cascadeOnDelete();
            $table->decimal('pct', 5, 2);
            $table->timestamps();
            $table->unique(['account_id'], 'acc_compliance_rules_unique');
        });
    }

    public function down(): void
    {
        foreach (['acc_compliance_rules', 'acc_account_classes', 'acc_fiscal_years', 'acc_cheque_leaves', 'acc_cheque_books', 'acc_bank_accounts', 'acc_cost_centers', 'acc_cost_center_groups', 'acc_accounts', 'acc_voucher_grants', 'acc_payment_modes'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('tx_banks', fn (Blueprint $table) => $table->dropColumn('is_treasury'));
    }
};
