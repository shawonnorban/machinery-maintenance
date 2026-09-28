<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material issued to a cutting programme, and the BOM variance it produced
 * (Inventory spec §9).
 *
 * One row per hand-over, the same shape the floor requisition uses: a
 * programme is rarely issued in one go, and each issue may come from a
 * different bin, lot or roll. The variance columns are a snapshot taken at
 * the moment of issue — what the bill of materials said this programme
 * would take, what had actually gone out by then, and the gap as a
 * percentage. Recomputing it later from today's BOM would quietly rewrite
 * history every time a consumption figure is corrected.
 *
 * `approval_status` / `approval_request_id` are the approval engine's own
 * columns: an issue that pushes the variance past the factory's tolerance
 * waits for a sign-off, and **the stock does not move until it has one**
 * (spec §9: "সীমার বাইরে গেলে Warning + Approval লাগবে"). That is why
 * `item_stock_transaction_id` is nullable — a pending issue has not posted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_material_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('cutting_program_id')->constrained('production_cutting_programs')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('item_code', 64);
            $table->string('item_name');
            $table->foreignUlid('uom_id')->constrained('units_of_measure')->restrictOnDelete();

            $table->foreignUlid('bin_id')->nullable()->constrained('bins')->restrictOnDelete();
            $table->string('lot_no', 64)->nullable();
            $table->foreignUlid('fabric_roll_id')->nullable()->constrained('fabric_rolls')->nullOnDelete();

            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->char('currency', 3)->default('BDT');
            // Null while the issue is waiting for approval.
            $table->ulid('item_stock_transaction_id')->nullable();

            // The variance snapshot, as at this issue.
            $table->decimal('required_qty', 18, 4)->default(0);
            $table->decimal('issued_to_date', 18, 4)->default(0);
            $table->decimal('variance_qty', 18, 4)->default(0);
            $table->decimal('variance_percent', 8, 2)->default(0);
            // Issued knowingly beyond the bill of materials (spec §9).
            $table->boolean('is_extra')->default(false);
            $table->text('reason')->nullable();

            $table->string('status', 24)->default('POSTED');
            $table->string('approval_status', 16)->default('NOT_REQUIRED');
            $table->ulid('approval_request_id')->nullable();

            $table->foreignUlid('issued_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'cutting_program_id'], 'production_issues_program_idx');
            $table->index(['company_id', 'item_id'], 'production_issues_item_idx');
            $table->index(['company_id', 'status'], 'production_issues_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_material_issues');
    }
};
