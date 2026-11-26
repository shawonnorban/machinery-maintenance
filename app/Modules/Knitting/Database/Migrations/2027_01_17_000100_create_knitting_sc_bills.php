<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Fabric Store (AnnotechERP 15/05), what sub-contract
 * work costs, as Brickland bills it:
 *
 * - In-House / Out-House Sub-Contract Bill and Out-House Yarn Dyeing Bill
 *   (SIB- / SOB- / YDB-…): one paper, its kind naming which programme a
 *   charge line is booked to (the in-house programme receipt, the out-house
 *   S/C programme, the Y/D programme). Draft → Submitted (claimed) →
 *   Approved (payable), or Cancelled; a bill quoting another as its Ref.Bill#
 *   marks that one Revised when it is submitted. Net = gross − deduction + VAT.
 * - Out-House Debit Memo (DMO-…): what an outside party is charged back for
 *   loss or damage, priced against the challan it was found on. Draft →
 *   Submitted → Approved (claimable) → Settled, or Cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_sc_bills', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('bill_no', 24);
            // IN_HOUSE, OUT_HOUSE, YD.
            $table->string('kind', 12);
            $table->date('bill_date');
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('party_bill_no', 60)->nullable();
            // The bill this one replaces; marked Revised when this one is submitted.
            $table->ulid('ref_bill_id')->nullable();
            $table->string('rev_descr', 300)->nullable();
            $table->foreignUlid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->decimal('gross', 16, 4)->default(0);
            $table->decimal('deduction', 16, 4)->default(0);
            $table->decimal('vat', 16, 4)->default(0);
            $table->decimal('net', 16, 4)->default(0);
            $table->text('remarks')->nullable();
            // DRAFT, SUBMITTED, APPROVED, REVISED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'bill_no']);
            $table->index(['company_id', 'kind', 'status']);
        });
        Schema::table('knit_sc_bills', function (Blueprint $table): void {
            $table->foreign('ref_bill_id')->references('id')->on('knit_sc_bills')->nullOnDelete();
        });

        Schema::create('knit_sc_bill_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bill_id')->constrained('knit_sc_bills')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            // One of the three, by the bill's kind.
            $table->foreignUlid('sc_receipt_id')->nullable()->constrained('knit_sc_receipts')->nullOnDelete();
            $table->foreignUlid('sc_program_id')->nullable()->constrained('knit_sc_programs')->nullOnDelete();
            $table->foreignUlid('yd_program_id')->nullable()->constrained('knit_yd_programs')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('description', 200)->nullable();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('shade', 40)->nullable();
            $table->string('challan_no', 40)->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16);
            $table->decimal('rate', 14, 4);
            $table->decimal('amount', 16, 4);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_sc_debit_memos', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('memo_no', 24);
            $table->date('memo_date');
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
            $table->string('reason', 300)->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('total', 16, 4)->default(0);
            // DRAFT, SUBMITTED, APPROVED, SETTLED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'memo_no']);
        });

        Schema::create('knit_sc_debit_memo_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('memo_id')->constrained('knit_sc_debit_memos')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            // FABRIC, COLLAR, CUFF, YARN.
            $table->string('part', 12)->nullable();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('colour_name', 80)->nullable();
            $table->string('description', 200);
            $table->string('challan_no', 40)->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16);
            $table->decimal('rate', 14, 4);
            $table->decimal('amount', 16, 4);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_sc_debit_memo_lines', 'knit_sc_debit_memos', 'knit_sc_bill_lines', 'knit_sc_bills'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
