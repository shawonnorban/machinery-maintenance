<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Trade & Export → Master Data (AnnotechERP 22/01): the reference lists the
 * commercial documents pick from (Inco Term, Payment Term …), each buyer's
 * notify parties and L/C applicants, the local exchange rates, and the bank
 * directory with its branches. Buyers and countries are Merchandise Desk's,
 * currencies Settings'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tx_reference_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('list', 24);
            $table->string('code', 24);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('short_name', 32);
            $table->unsignedInteger('display_order');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'list', 'code'], 'tx_reference_entries_unique');
        });

        Schema::create('tx_notify_parties', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->text('address');
            $table->foreignUlid('country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->string('post_code', 16)->nullable();
            $table->string('po_box', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('fax', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('web_url')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'tx_notify_parties_code_unique');
        });

        Schema::create('tx_lc_applicants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->text('address');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Commercial local exchange rates: the rate a currency is converted at, for a purpose, from a day.
        Schema::create('tx_exchange_rates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('rate_for', 24)->nullable(); // EXPORT_REALIZATION | IMPORT_PAYMENT | LOCAL_SALES | CASH_INCENTIVE
            $table->string('currency', 3);
            $table->decimal('rate', 16, 6);
            $table->date('effective_date');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'currency', 'effective_date']);
        });

        Schema::create('tx_banks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('prefix', 16)->nullable();
            $table->boolean('is_local')->default(true);
            $table->foreignUlid('country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->string('swift_code', 16)->nullable();
            $table->string('iban', 40)->nullable();
            $table->string('call_center', 32)->nullable();
            $table->string('web_url')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'tx_banks_code_unique');
        });

        Schema::create('tx_bank_branches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bank_id')->constrained('tx_banks')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('swift_code', 16)->nullable();
            $table->string('routing_no', 16)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['bank_id', 'code'], 'tx_bank_branches_unique');
        });
    }

    public function down(): void
    {
        foreach (['tx_bank_branches', 'tx_banks', 'tx_exchange_rates', 'tx_lc_applicants', 'tx_notify_parties', 'tx_reference_entries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
