<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Yarn Store (AnnotechERP 15/03), the yarn going out:
 *
 * - `knit_yarn_issue_reqs` + lines: the Yarn Requisition Entry — what the
 *   floor asks the store for, one count against one programme per line;
 *   Open → Partly Issued → Fully Issued, or Closed / Cancelled.
 * - `knit_yarn_issues` + lines: Yarn Issue Entry — yarn off a lot against a
 *   requisition line, posted on save.
 * - `knit_yarn_issue_returns` + lines: Yarn Issue Return against Indent —
 *   unused yarn back off the floor (Ret sent, Recv counted); Draft → Issued
 *   → Accepted (posted, the issue credited) / Rejected; Cancelled.
 * - `knit_yarn_party_challans` + lines: Party-Wise Yarn Issue Challan —
 *   yarn out to a sub-contract party; posted from Issued until Rejected or
 *   Cancelled.
 * - `knit_yarn_replacements` + lines: Supplier Yarn Replacement Register —
 *   yarn back to a spinner on a replacement claim; Draft → Submitted
 *   (posted) → Accepted; Cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_yarn_issue_reqs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            $table->date('req_date');
            $table->string('req_type', 48);
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('party_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->string('program_no', 40)->nullable();
            $table->text('remarks')->nullable();
            // OPEN, PARTLY_ISSUED, FULLY_ISSUED, CLOSED, CANCELLED.
            $table->string('status', 16)->default('OPEN');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
            $table->index(['company_id', 'status', 'req_date']);
        });

        Schema::create('knit_yarn_issue_req_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('req_id')->constrained('knit_yarn_issue_reqs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->string('uom', 16)->default('kg');
            $table->decimal('req_qty', 14, 4);
            $table->decimal('kp_alloc', 14, 4)->nullable();
            // Issued less what came back on accepted returns.
            $table->decimal('issued_qty', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('issue_no', 24);
            $table->date('issue_date');
            $table->foreignUlid('req_id')->constrained('knit_yarn_issue_reqs')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'issue_no']);
            $table->index(['company_id', 'issue_date']);
        });

        Schema::create('knit_yarn_issue_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('issue_id')->constrained('knit_yarn_issues')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('req_line_id')->constrained('knit_yarn_issue_req_lines')->restrictOnDelete();
            $table->foreignUlid('lot_id')->constrained('knit_yarn_lots')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->unsignedInteger('cones')->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_issue_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('return_no', 24);
            $table->date('return_date');
            $table->string('req_type', 48);
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('req_id')->nullable()->constrained('knit_yarn_issue_reqs')->nullOnDelete();
            $table->string('return_by', 120);
            $table->text('remarks')->nullable();
            // DRAFT, ISSUED, ACCEPTED, REJECTED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no']);
            $table->index(['company_id', 'status', 'return_date']);
        });

        Schema::create('knit_yarn_issue_return_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('return_id')->constrained('knit_yarn_issue_returns')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('issue_line_id')->nullable()->constrained('knit_yarn_issue_lines')->nullOnDelete();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->string('uom', 16)->default('kg');
            $table->decimal('ret_qty', 14, 4);
            $table->decimal('recv_qty', 14, 4)->nullable();
            // The stock line the counted weight went back to, once accepted.
            $table->foreignUlid('lot_id')->nullable()->constrained('knit_yarn_lots')->nullOnDelete();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_party_challans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('challan_date');
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, ISSUED, ACCEPTED, REJECTED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
            $table->index(['company_id', 'status', 'challan_date']);
        });

        Schema::create('knit_yarn_party_challan_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('challan_id')->constrained('knit_yarn_party_challans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('lot_id')->constrained('knit_yarn_lots')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->nullable();
            $table->unsignedInteger('cones')->nullable();
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_yarn_replacements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('challan_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('claim_ref', 40)->nullable();
            $table->string('return_type', 24);
            $table->string('lc_no', 40)->nullable();
            $table->string('lot_ref', 40)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, SUBMITTED, ACCEPTED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
            $table->index(['company_id', 'status', 'challan_date']);
        });

        Schema::create('knit_yarn_replacement_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('replacement_id')->constrained('knit_yarn_replacements')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('lot_id')->constrained('knit_yarn_lots')->restrictOnDelete();
            $table->unsignedInteger('cartons')->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'knit_yarn_replacement_lines', 'knit_yarn_replacements', 'knit_yarn_party_challan_lines', 'knit_yarn_party_challans',
            'knit_yarn_issue_return_lines', 'knit_yarn_issue_returns', 'knit_yarn_issue_lines', 'knit_yarn_issues',
            'knit_yarn_issue_req_lines', 'knit_yarn_issue_reqs',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
