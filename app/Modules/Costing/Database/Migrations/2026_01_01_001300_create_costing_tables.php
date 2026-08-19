<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cost entries: ERD Section 14, SRS 23-24.
 *
 * Append-only after posting, exactly like the inventory ledger and for the same
 * reason: a cost figure that can be edited is a cost figure somebody will edit
 * to match a budget. A correction is a REVERSAL row plus a new entry, so the
 * history shows both what was posted and what was done about it.
 *
 * Entries derived from labour and parts are written by the system, never by
 * users, so a work order's cost cannot drift from the records underneath it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Null company_id = platform-seeded, visible to every tenant.
            $table->foreignUlid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->string('code', 48);
            // Which lifecycle bucket this rolls up into (SRS 23).
            $table->string('lifecycle_bucket', 32)->default('MAINTENANCE');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'cost_categories_code_unique');
        });

        Schema::create('cost_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // Every cost belongs to a machine. A maintenance spend that cannot
            // be attributed to an asset cannot answer "what is this machine
            // costing us", which is the question the module exists for.
            $table->foreignUlid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignUlid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignUlid('breakdown_id')->nullable()->constrained('breakdowns')->nullOnDelete();
            $table->foreignUlid('cost_category_id')->constrained('cost_categories')->restrictOnDelete();

            $table->decimal('amount', 18, 4);
            $table->char('currency', 3)->default('BDT');
            // Frozen at post time. A later rate change must never rewrite what
            // a closed period reported (SRS 24, ERD Section 14 rule 2).
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('base_amount', 18, 4);

            $table->dateTime('occurred_at', 3);
            $table->string('description')->nullable();
            // LABOR | PARTS | EXTERNAL_SERVICE | VENDOR | TRANSPORT | MANUAL | REVERSAL
            $table->string('source_type', 32);
            $table->string('source_reference_type', 48)->nullable();
            $table->ulid('source_reference_id')->nullable();
            $table->ulid('vendor_id')->nullable();
            $table->string('invoice_reference')->nullable();

            $table->dateTime('posted_at', 3);
            $table->foreignUlid('posted_by')->nullable();
            $table->foreignUlid('reverses_cost_entry_id')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'asset_id', 'occurred_at'], 'cost_entries_asset_idx');
            $table->index(['company_id', 'work_order_id'], 'cost_entries_wo_idx');
            $table->index(['company_id', 'cost_category_id'], 'cost_entries_category_idx');
            /*
             * A derived entry is rewritten in place when its source changes, so
             * one labour entry can never produce two live cost rows.
             *
             * MySQL treats NULLs as distinct in a unique index, and here that is
             * exactly what is wanted: a manually posted entry has no source
             * reference and is unconstrained, while a derived one is unique per
             * source. (Elsewhere that same behaviour was a bug — on soft-delete
             * uniqueness — so it is stated rather than left to be rediscovered.)
             */
            $table->unique(
                ['company_id', 'source_reference_type', 'source_reference_id', 'is_reversal'],
                'cost_entries_source_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_entries');
        Schema::dropIfExists('cost_categories');
    }
};
