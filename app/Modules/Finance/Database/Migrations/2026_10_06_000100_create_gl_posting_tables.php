<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General ledger postings (Shared Systems spec §2, §5.1).
 *
 * The spec is explicit that the chart of accounts has not been signed off,
 * and equally explicit about how to build anyway: the event → debit/credit
 * mapping is **read from a table**, never hardcoded, and it is seeded with
 * placeholder accounts. When finance signs off, data changes and no code
 * or migration does.
 *
 * Two tables, for two different jobs:
 *
 *  - `gl_event_mappings` is configuration: one row per business event,
 *    pointing at two accounts by id. Renaming or renumbering an account
 *    later cannot orphan a posting, because nothing stores a code.
 *  - `gl_journal_entries` + `gl_journal_lines` are the postings themselves,
 *    kept as a header and its lines rather than two columns on one row,
 *    because the settlement event already credits two accounts and a
 *    two-column shape would have to be rebuilt the first time finance asks
 *    for a third.
 *
 * `(company_id, event_type, source_type, source_id)` is unique: a GRN
 * completed once posts once, and a retried request cannot double the books.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_event_mappings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('event_type', 48);
            $table->foreignUlid('debit_account_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->foreignUlid('credit_account_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            // A second credit, used by settlement: payable against an
            // advance and the bank. Null everywhere else.
            $table->foreignUlid('secondary_credit_account_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'event_type'], 'gl_event_mappings_event_unique');
        });

        Schema::create('gl_journal_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->string('event_type', 48);
            $table->date('entry_date');
            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('BDT');
            // The document this came from, and the number an accountant
            // would look it up by.
            $table->string('source_type', 48);
            $table->ulid('source_id');
            $table->string('source_no', 48)->nullable();
            $table->text('memo')->nullable();
            $table->foreignUlid('posted_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'event_type', 'source_type', 'source_id'],
                'gl_journal_entries_source_unique',
            );
            $table->index(['company_id', 'entry_date'], 'gl_journal_entries_date_idx');
        });

        Schema::create('gl_journal_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gl_journal_entry_id')->constrained('gl_journal_entries')->cascadeOnDelete();
            $table->foreignUlid('gl_account_id')->nullable()->constrained('gl_accounts')->nullOnDelete();
            // DEBIT | CREDIT
            $table->string('side', 8);
            $table->decimal('amount', 18, 4);
            $table->string('memo', 255)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'gl_account_id'], 'gl_journal_lines_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_journal_lines');
        Schema::dropIfExists('gl_journal_entries');
        Schema::dropIfExists('gl_event_mappings');
    }
};
