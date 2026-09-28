<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The store's half of a floor requisition (Floor/Store Requisition spec
 * §4–§7, §9).
 *
 * Phase 1 built the form and stopped at SUBMITTED: `approved_qty` was never
 * written and nothing ever left the shelf. This adds the review, the issue
 * and what happens when the store cannot fill the request — the shortage,
 * the purchase requisition it raises automatically, and the claim it stakes
 * on the stock that is coming.
 *
 * `floor_requisition_issues` is the row per physical hand-over. A line can
 * be issued more than once (part now, the rest when the goods arrive) and
 * each issue names the bin, the lot or the roll it came from and the ledger
 * transaction it produced — which is what makes "where did this fabric come
 * from" answerable months later, and what a return posts back against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('floor_requisitions', function (Blueprint $table): void {
            $table->foreignUlid('reviewed_by')->nullable()->after('requested_by');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->timestamp('closed_at')->nullable()->after('reviewed_at');
            $table->text('rejection_reason')->nullable()->after('purpose');
            // The purchase requisition raised for whatever could not be
            // issued (spec §5.3).
            $table->foreignUlid('shortage_pr_id')->nullable()->after('rejection_reason');
        });

        Schema::table('floor_requisition_lines', function (Blueprint $table): void {
            $table->decimal('issued_qty', 18, 4)->default(0)->after('approved_qty');
            $table->decimal('returned_qty', 18, 4)->default(0)->after('issued_qty');
            $table->decimal('shortage_qty', 18, 4)->default(0)->after('returned_qty');
            $table->string('status', 24)->default('PENDING')->after('shortage_qty');
            // The earmark raised for this line's shortage.
            $table->foreignUlid('item_reservation_id')->nullable()->after('status');
            $table->text('rejection_reason')->nullable()->after('purpose');
        });

        Schema::create('floor_requisition_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('floor_requisition_line_id')->constrained('floor_requisition_lines')->cascadeOnDelete();
            $table->foreignUlid('bin_id')->constrained('bins')->restrictOnDelete();
            $table->string('lot_no', 64)->nullable();
            $table->foreignUlid('fabric_roll_id')->nullable()->constrained('fabric_rolls')->nullOnDelete();

            $table->decimal('quantity', 18, 4);
            // What it cost when it left the store — a return posts back at
            // this price, not at whatever the average has drifted to since.
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->ulid('item_stock_transaction_id')->nullable();

            $table->decimal('returned_qty', 18, 4)->default(0);
            $table->timestamp('returned_at')->nullable();
            $table->ulid('return_transaction_id')->nullable();

            $table->foreignUlid('issued_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'floor_requisition_line_id'], 'fr_issues_line_idx');
            $table->index(['company_id', 'issued_at'], 'fr_issues_issued_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('floor_requisition_issues');

        Schema::table('floor_requisition_lines', function (Blueprint $table): void {
            $table->dropColumn([
                'issued_qty', 'returned_qty', 'shortage_qty', 'status',
                'item_reservation_id', 'rejection_reason',
            ]);
        });

        Schema::table('floor_requisitions', function (Blueprint $table): void {
            $table->dropColumn(['reviewed_by', 'reviewed_at', 'closed_at', 'rejection_reason', 'shortage_pr_id']);
        });
    }
};
