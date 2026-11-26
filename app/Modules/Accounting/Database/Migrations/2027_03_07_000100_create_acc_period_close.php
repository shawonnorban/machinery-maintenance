<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance & Ledger → Period Close (AnnotechERP 23/04):
 *
 * - `acc_stock_closings`: the closing stock value of a stock head, one figure
 *   per company, year, month and code.
 * - `acc_bank_lines`: a posted voucher line brought onto a bank account's
 *   reconciliation — its cheque, whether the bank has cleared it and when.
 * - `acc_fiscal_year_closings`: a fiscal year run through the closing
 *   process — what was posted in it and its result; the year is then closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acc_stock_closings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('code', 24);
            $table->string('particular', 160);
            $table->decimal('closing', 18, 4)->default(0);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'year', 'month', 'code']);
        });

        Schema::create('acc_bank_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('voucher_line_id')->unique()->constrained('acc_voucher_lines')->cascadeOnDelete();
            $table->foreignUlid('bank_account_id')->constrained('acc_bank_accounts')->restrictOnDelete();
            $table->string('cheque_no', 48)->nullable();
            $table->date('bank_date')->nullable();
            $table->boolean('cleared')->default(false);
            $table->string('remarks', 300)->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('acc_fiscal_year_closings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('fiscal_year_id')->unique()->constrained('acc_fiscal_years')->cascadeOnDelete();
            $table->unsignedInteger('vouchers_posted')->default(0);
            $table->decimal('closing_balance', 18, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['acc_fiscal_year_closings', 'acc_bank_lines', 'acc_stock_closings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
