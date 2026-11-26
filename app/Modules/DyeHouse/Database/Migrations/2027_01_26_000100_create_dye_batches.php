<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Dye House → Batch (Bulk) (AnnotechERP 17/03):
 *
 * - `dye_batch_plans`: the boards batches are laid out on — machines down the
 *   side, the clock across the top.
 * - `dye_batches` / `dye_batch_items`: the batch card (B-YYMM####) and what it
 *   is made of — grey fabric, collar & cuff, trims or finished fabric. A card
 *   comes from a released Fabric Batch Plan, by hand, off finished fabric, out
 *   of a split, or as a colour replacement of another.
 * - `dye_grey_indents` / `_lines` / `dye_grey_issues`: grey fabric called off
 *   the batch store against a card, and what the store issued, lot by lot.
 * - `dye_pt_programmes`, `dye_pt_challans`, `dye_pt_receipts`, `dye_pt_bills`:
 *   pre-treatment sent out to a subcontractor, despatched, received back and
 *   billed.
 * - `dye_grey_order_transfers`, `dye_colour_transfers`, `dye_leftover_transfers`:
 *   grey moved between orders in the batch store, cards reassigned to another
 *   shade, and fabric released into left-over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dye_batch_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name');
            $table->date('plan_date');
            // Blank: every company the plan's batches belong to.
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('scope', 8); // BULK | SAMPLE
            $table->unsignedSmallInteger('scale_hours');
            $table->decimal('cell_width', 5, 2);
            $table->timestamp('window_from')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'dye_batch_plans_code_unique');
        });

        Schema::create('dye_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('batch_no', 24);
            $table->date('batch_date');
            $table->string('scope', 8)->default('BULK'); // BULK | SAMPLE
            $table->string('source', 12); // PLAN | MANUAL | FINISHED | SPLIT | COLOUR
            $table->foreignUlid('fp_batch_plan_id')->nullable()->constrained('fp_batch_plans')->nullOnDelete();
            $table->foreignUlid('plan_id')->nullable()->constrained('dye_batch_plans')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('order_no', 64)->nullable();
            $table->string('style_ref', 120)->nullable();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->foreignUlid('colour_group_id')->nullable()->constrained('dye_colour_groups')->nullOnDelete();
            $table->string('dyeing_method', 24)->nullable();
            $table->foreignUlid('dye_machine_id')->nullable()->constrained('dye_machines')->nullOnDelete();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('plan_qty', 14, 2)->default(0);
            $table->decimal('card_qty', 14, 2)->default(0);
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('manual_ref_no', 40)->nullable();
            $table->date('manual_ref_date')->nullable();
            $table->timestamp('planned_start')->nullable();
            $table->timestamp('planned_end')->nullable();
            $table->string('status', 10)->default('OPEN'); // OPEN | REPLACED | CLOSED
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'batch_no'], 'dye_batches_no_unique');
            $table->index(['company_id', 'plan_id']);
        });
        Schema::table('dye_batches', function (Blueprint $table): void {
            $table->foreignUlid('parent_id')->nullable()->after('fp_batch_plan_id')->constrained('dye_batches')->nullOnDelete();
        });

        Schema::create('dye_batch_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('dye_batches')->cascadeOnDelete();
            $table->string('kind', 12); // GREY | COLLAR_CUFF | TRIMS | FINISHED
            $table->string('description');
            $table->decimal('qty_kg', 14, 3)->default(0);
            $table->decimal('issue_kg', 14, 3)->default(0);
            $table->unsignedInteger('rolls')->default(0);
            $table->unsignedInteger('issue_rolls')->default(0);
            $table->unsignedInteger('pcs')->default(0);
            $table->unsignedInteger('issue_pcs')->default(0);
            $table->decimal('yds', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('dye_grey_indents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->date('req_date');
            $table->string('req_type', 10); // BULK | FINISHED
            $table->foreignUlid('batch_id')->constrained('dye_batches')->cascadeOnDelete();
            $table->foreignUlid('colour_group_id')->nullable()->constrained('dye_colour_groups')->nullOnDelete();
            $table->decimal('proj_rate', 12, 2)->nullable();
            $table->foreignUlid('store_id')->constrained('stores');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no'], 'dye_grey_indents_no_unique');
        });

        Schema::create('dye_grey_indent_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('indent_id')->constrained('dye_grey_indents')->cascadeOnDelete();
            $table->foreignUlid('batch_item_id')->nullable()->constrained('dye_batch_items')->nullOnDelete();
            $table->string('kind', 12);
            $table->string('description');
            $table->decimal('req_kg', 14, 3)->default(0);
            $table->unsignedInteger('rolls')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // What the batch store issued against a requisition, off one grey stock line.
        Schema::create('dye_grey_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('indent_id')->constrained('dye_grey_indents')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('knit_grey_lots');
            $table->date('issue_date');
            $table->decimal('qty_kg', 14, 3);
            $table->unsignedInteger('rolls')->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dye_pt_challans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers');
            $table->date('challan_date');
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no'], 'dye_pt_challans_no_unique');
        });

        Schema::create('dye_pt_programmes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 24);
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers');
            $table->date('issue_date');
            $table->date('exp_delivery_date')->nullable();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            // Filled in when the challan that carries it is written.
            $table->foreignUlid('challan_id')->nullable()->constrained('dye_pt_challans')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'program_no'], 'dye_pt_programmes_no_unique');
        });

        Schema::create('dye_pt_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('mrr_no', 24);
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers');
            $table->foreignUlid('transfer_party_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->date('issue_date');
            $table->string('qc_no', 40)->nullable();
            $table->foreignUlid('challan_id')->nullable()->constrained('dye_pt_challans')->nullOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mrr_no'], 'dye_pt_receipts_no_unique');
        });

        Schema::create('dye_pt_bills', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers');
            $table->string('bill_no', 40);
            $table->date('bill_date');
            $table->decimal('amount', 14, 2);
            $table->string('ref_bill_no', 40)->nullable();
            $table->unsignedSmallInteger('revision_no')->nullable();
            $table->text('revision_descr')->nullable();
            $table->string('status', 16); // DRAFT | WAITING_STORE | APPROVED | COMPLETED | CANCELLED
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'supplier_id']);
        });

        Schema::create('dye_grey_order_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transfer_no', 24);
            $table->date('transfer_date');
            $table->string('trans_type', 20); // BULK_TO_BULK | BULK_TO_SAMPLE | SAMPLE_TO_BULK | BULK_TO_LEFTOVER
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('from_order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('to_order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('status', 2); // 10 | 30 | 50 | 90 | 99
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'transfer_no'], 'dye_grey_order_transfers_no_unique');
        });

        Schema::create('dye_colour_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transfer_no', 24);
            $table->foreignUlid('batch_id')->constrained('dye_batches');
            $table->foreignUlid('new_batch_id')->nullable()->constrained('dye_batches')->nullOnDelete();
            $table->date('transfer_date');
            $table->string('new_batch_no', 24);
            $table->string('lot_no', 40)->nullable();
            $table->date('batch_date');
            $table->decimal('batch_qty', 14, 2);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'transfer_no'], 'dye_colour_transfers_no_unique');
        });

        Schema::create('dye_leftover_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transfer_no', 24);
            $table->date('transfer_date');
            $table->string('reason', 16); // BATCH_END | ORDER_CANCELLED | SHORT_BATCH | EXCESS_KNITTING | QUALITY_HOLD
            $table->string('source', 120);
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->decimal('qty', 14, 2);
            $table->string('status', 12); // DRAFT | REQUESTED | APPROVED | TRANSFERRED | CANCELLED
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'transfer_no'], 'dye_leftover_transfers_no_unique');
        });
    }

    public function down(): void
    {
        foreach (['dye_leftover_transfers', 'dye_colour_transfers', 'dye_grey_order_transfers', 'dye_pt_bills', 'dye_pt_receipts', 'dye_pt_programmes', 'dye_pt_challans',
            'dye_grey_issues', 'dye_grey_indent_lines', 'dye_grey_indents', 'dye_batch_items', 'dye_batches', 'dye_batch_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
