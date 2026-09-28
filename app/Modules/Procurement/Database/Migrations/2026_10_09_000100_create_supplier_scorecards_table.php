<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier performance scorecards (Procurement spec §15).
 *
 * A scorecard is a **snapshot of a period**, stored rather than computed
 * on demand. Two reasons: the figures behind it move — a claim settled in
 * November changes what October looked like — and a scorecard that
 * silently rewrites itself is useless in the conversation it exists for,
 * which is a factory sitting across the table from a mill.
 *
 * Every component is nullable. A supplier nobody ordered from in the
 * period has no on-time percentage, and a `0` there would read as "never
 * delivered on time" — the opposite of "we have nothing to say". The same
 * goes for price competitiveness, which needs a second supplier of the
 * same item to mean anything at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_scorecards', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');

            // On-time delivery: receipts against an order that promised a
            // date. An order with no promised date is left out — there is
            // nothing to be late against.
            $table->unsignedInteger('receipt_count')->default(0);
            $table->unsignedInteger('dated_receipt_count')->default(0);
            $table->unsignedInteger('on_time_count')->default(0);
            $table->decimal('on_time_percent', 6, 2)->nullable();

            // Quality: measured by value, because a rejected roll and a
            // rejected button are not the same failure.
            $table->decimal('received_value', 18, 4)->default(0);
            $table->decimal('rejected_value', 18, 4)->default(0);
            $table->decimal('quality_reject_percent', 6, 2)->nullable();

            // Price: this supplier's average against what everyone charged
            // for the same items. Null when nobody else supplied them.
            $table->decimal('price_index', 8, 4)->nullable();
            $table->decimal('price_score', 6, 2)->nullable();

            $table->unsignedInteger('claim_count')->default(0);
            $table->decimal('claim_frequency_percent', 6, 2)->nullable();

            // The weighted mean of whichever components had data.
            $table->decimal('overall_score', 6, 2)->nullable();
            // Which ones did, so a score of 70 can be read honestly.
            $table->json('components_json')->nullable();

            $table->timestamp('computed_at');
            $table->foreignUlid('computed_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'vendor_id', 'period_start', 'period_end'],
                'supplier_scorecards_period_unique',
            );
            $table->index(['company_id', 'period_end'], 'supplier_scorecards_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_scorecards');
    }
};
