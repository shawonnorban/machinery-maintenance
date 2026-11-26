<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dye House → Chemical Store (AnnotechERP 17/06): the dye and chemical book.
 *
 * - `dye_chem_lots`: a lot of one item in one store (lot number, expiry, the
 *   rate it came in at). Two drums of the same chemical from different
 *   batches are two lots, and the store counts them apart.
 * - `dye_chem_moves`: every movement on a lot, signed (+ in, − out). A lot's
 *   stock is its moves added up; nothing else holds a balance.
 * - `dye_chem_openings`: Chemical Opening Balance — one row per lot per store.
 * - `dye_chem_receipts` + lines: Daily Dye & Chemical Receipt (MRR-), Dye
 *   Chemical Loan Receipt (MRR-DC-) and Chemical Loan Return from Party
 *   (RTN-LP-): stock in, counted only once the store finalises the paper.
 * - `dye_chem_returns` + lines: Dye & Chemical Receipt Return (RTN-) — back
 *   to the supplier against a receipt, off stock once finalised.
 * - `dye_chem_issues` + lines: every paper that takes stock out — store
 *   transfer (REQ-ST-), print (IS-PR-), machine wash & finishing (IS-MW-),
 *   batch programme (IS-BP-), sales (IS-SL-), loan issue (IS-LN-) and loan
 *   return (IS-LR-) — what is asked, and what the store has issued against it.
 * - `dye_chem_loan_openings`: Chemical Loan Opening Balance — what each
 *   supplier had lent us and we them, per item, on opening day.
 * - `dye_chem_cs` + lines + quotes: Chemical Comparative Statement — the
 *   suppliers' rates laid side by side per item, and who each is bought from.
 * - `dye_chem_closings` + lines: Chemical Month Closing — a struck month and
 *   every lot's balance at its end; the month takes no more movement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dye_chem_lots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('lot_no', 40);
            $table->date('exp_date')->nullable();
            $table->string('uom', 16)->nullable();
            $table->decimal('rate_bdt', 14, 4)->default(0);
            $table->string('source', 8)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'store_id', 'item_id', 'lot_no']);
            $table->index(['company_id', 'item_id']);
        });
        Schema::create('dye_chem_moves', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('dye_chem_lots')->restrictOnDelete();
            $table->date('move_date');
            // OPENING, RECEIPT, LOAN_IN, LOAN_BACK, RETURN, TRANSFER_OUT, TRANSFER_IN, PRINT, WASH, BATCH, SALE, LOAN_OUT, LOAN_RETURN, REQUISITION.
            $table->string('kind', 16);
            $table->string('doc_no', 30);
            $table->ulid('doc_line_id')->nullable();
            $table->decimal('qty', 16, 4);
            $table->decimal('value', 18, 4)->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['lot_id', 'move_date']);
            $table->index(['company_id', 'kind', 'doc_no']);
            $table->index(['company_id', 'move_date']);
        });
        Schema::create('dye_chem_openings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('ob_date');
            $table->foreignUlid('category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->decimal('usd_rate', 10, 4)->nullable();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('source', 8);
            $table->boolean('auto_lot')->default(false);
            $table->string('lot_no', 40);
            $table->date('exp_date')->nullable();
            $table->decimal('pack_qty', 14, 4)->nullable();
            $table->string('pack_uom', 16)->nullable();
            $table->decimal('qty_per_pack', 14, 4)->nullable();
            $table->decimal('loose_qty', 14, 4)->nullable();
            $table->decimal('total_qty', 16, 4);
            $table->string('uom', 16)->nullable();
            $table->decimal('rate_bdt', 14, 4)->default(0);
            $table->foreignUlid('lot_id')->nullable()->constrained('dye_chem_lots')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'store_id', 'item_id', 'lot_no']);
            $table->index(['company_id', 'ob_date']);
        });
        Schema::create('dye_chem_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // PURCHASE (MRR-), LOAN_IN (MRR-DC-), LOAN_BACK (RTN-LP-).
            $table->string('kind', 10);
            $table->string('doc_no', 24);
            $table->date('doc_date');
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('source', 8)->nullable();
            $table->string('challan_no', 60)->nullable();
            $table->string('lc_no', 60)->nullable();
            $table->string('pr_no', 40)->nullable();
            $table->string('po_no', 40)->nullable();
            $table->string('req_type', 80)->nullable();
            $table->string('pay_method', 60)->nullable();
            $table->string('return_type', 12)->nullable();
            $table->string('received_by', 80)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, RECEIVED, VERIFIED, CANCELLED.
            $table->string('status', 10)->default('DRAFT');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'doc_no']);
            $table->index(['company_id', 'kind', 'doc_date']);
        });
        Schema::create('dye_chem_receipt_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('dye_chem_receipts')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('lot_no', 40);
            $table->date('exp_date')->nullable();
            $table->decimal('qty', 16, 4);
            $table->string('uom', 16)->nullable();
            $table->decimal('rate_bdt', 14, 4)->default(0);
            $table->string('remarks', 255)->nullable();
            $table->foreignUlid('lot_id')->nullable()->constrained('dye_chem_lots')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_chem_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('return_no', 24);
            $table->date('return_date');
            $table->foreignUlid('receipt_id')->constrained('dye_chem_receipts')->restrictOnDelete();
            // SUPPLIER, DAMAGE, EXCESS, WRONG_ITEM.
            $table->string('return_type', 12);
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT, FINALIZED, CANCELLED.
            $table->string('status', 10)->default('DRAFT');
            $table->timestamp('finalized_at')->nullable();
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no']);
            $table->index(['company_id', 'return_date']);
        });
        Schema::create('dye_chem_return_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('return_id')->constrained('dye_chem_returns')->cascadeOnDelete();
            $table->foreignUlid('receipt_line_id')->constrained('dye_chem_receipt_lines')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('lot_no', 40);
            $table->decimal('qty', 16, 4);
            $table->string('uom', 16)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_chem_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            // TRANSFER, PRINT, WASH, BATCH, SALE, LOAN_OUT, LOAN_RETURN.
            $table->string('kind', 12);
            $table->string('issue_no', 24);
            $table->date('issue_date');
            $table->string('req_type', 120);
            $table->foreignUlid('party_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('to_store_id')->nullable()->constrained('stores')->restrictOnDelete();
            $table->string('gate_pass', 40)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('print_type', 60)->nullable();
            $table->foreignUlid('batch_id')->nullable()->constrained('dye_batches')->nullOnDelete();
            $table->foreignUlid('dye_machine_id')->nullable()->constrained('dye_machines')->nullOnDelete();
            $table->string('process_type', 60)->nullable();
            $table->boolean('is_reprocess')->default(false);
            // Sales To, the loan's party, or who a borrowed loan goes back to.
            $table->foreignUlid('supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->text('remarks')->nullable();
            // A transfer: WAITING_VERIFICATION, VERIFIED, PARTIAL, ISSUED, CANCELLED; every other issue: WAITING, PARTIAL, ISSUED, CANCELLED.
            $table->string('status', 22);
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignUlid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'issue_no']);
            $table->index(['company_id', 'kind', 'issue_date']);
        });
        Schema::create('dye_chem_issue_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('issue_id')->constrained('dye_chem_issues')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            // Blank: the store issues from the item's lots, the soonest to expire first.
            $table->string('lot_no', 40)->nullable();
            $table->decimal('required_qty', 16, 4);
            $table->decimal('issued_qty', 16, 4)->default(0);
            $table->string('uom', 16)->nullable();
            $table->decimal('rate_bdt', 14, 4)->nullable();
            $table->string('remarks', 255)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_chem_loan_openings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('ob_date');
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->string('uom', 16)->nullable();
            $table->decimal('loan_received', 16, 4)->default(0);
            $table->decimal('loan_issued', 16, 4)->default(0);
            $table->boolean('is_final')->default(false);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'supplier_id', 'item_id']);
        });
        Schema::create('dye_chem_cs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('cs_no', 24);
            $table->date('cs_date');
            $table->foreignUlid('category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->foreignUlid('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->string('mou_code', 40)->nullable();
            $table->decimal('usd_rate', 10, 4)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, SUBMITTED, APPROVED, REJECTED, CLOSED.
            $table->string('status', 10)->default('DRAFT');
            $table->string('decision_note', 255)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'cs_no']);
            $table->index(['company_id', 'status', 'cs_date']);
        });
        Schema::create('dye_chem_cs_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('cs_id')->constrained('dye_chem_cs')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->string('uom', 16)->nullable();
            $table->foreignUlid('chosen_supplier_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('dye_chem_cs_quotes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('cs_line_id')->constrained('dye_chem_cs_lines')->cascadeOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->decimal('rate_usd', 14, 4)->nullable();
            $table->decimal('rate_bdt', 14, 4);
            $table->timestamps();
            $table->unique(['cs_line_id', 'supplier_id']);
        });
        Schema::create('dye_chem_closings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7);
            $table->text('remarks')->nullable();
            $table->unsignedInteger('lots')->default(0);
            $table->decimal('closing_qty', 18, 4)->default(0);
            $table->decimal('closing_value', 20, 4)->default(0);
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'period']);
        });
        Schema::create('dye_chem_closing_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('closing_id')->constrained('dye_chem_closings')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('dye_chem_lots')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->decimal('value', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['dye_chem_closing_lines', 'dye_chem_closings', 'dye_chem_cs_quotes', 'dye_chem_cs_lines', 'dye_chem_cs', 'dye_chem_loan_openings', 'dye_chem_issue_lines', 'dye_chem_issues',
            'dye_chem_return_lines', 'dye_chem_returns', 'dye_chem_receipt_lines', 'dye_chem_receipts', 'dye_chem_openings', 'dye_chem_moves', 'dye_chem_lots'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
