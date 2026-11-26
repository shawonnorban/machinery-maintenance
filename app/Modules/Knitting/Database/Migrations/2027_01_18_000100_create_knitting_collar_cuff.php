<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knit Production → Collar/Cuff (AnnotechERP 15/06), flat-knit parts as
 * Brickland tracks them:
 *
 * - `knit_cc_requirements` (CCR-…): Flat Knit Requirement — a part (collar,
 *   cuff, placket, neck rib, tape) a job needs, in-house and sub-contract
 *   pieces asked apart; Open until the merchandiser closes it.
 * - `knit_cc_productions`: Collar & Cuff Output Entry — what came off the
 *   flat-bed machines against a requirement, a day, a source (in-house /
 *   S/C out-house), with the rejects kept beside the accepted pieces.
 * - `knit_cc_programs` + lines (CCP-…): Out-House Collar & Cuff Programme —
 *   parts placed on an outside party at a rate per piece. Draft → Submitted
 *   → Approved → In Production → Closed, or Cancelled; received follows the
 *   S/C receive challans the store counts in.
 * - `knit_cc_yarn_reqs` + lines (CYS- solid / CYD- yarn-dyed): Collar & Cuff
 *   Yarn Indent. Draft → Submitted → Approved → Partly Issued / Issued, or
 *   Closed / Cancelled; `knit_cc_yarn_issues` are the store's releases
 *   against a line (CYS-…/n), each posted off a yarn stock line.
 * - `knit_cc_challans` + lines (CCB- bulk / CCS- sample): Collar & Cuff
 *   Store Receipt — pieces moved between the floor and the store. Draft →
 *   Issued → Received at Store, or Cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_cc_requirements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            // BULK, SAMPLE, DEVELOP.
            $table->string('req_type', 12);
            // A fabric booking category code (FP01 …).
            $table->string('category', 40)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('work_style_no', 60)->nullable();
            // YYYY-MM.
            $table->string('ship_month', 7)->nullable();
            // COLLAR, CUFF, PLACKET, NECK_RIB, TAPE.
            $table->string('item', 12);
            $table->string('description', 200);
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('size_desc', 80)->nullable();
            $table->string('gauge', 40)->nullable();
            $table->decimal('req_in_house', 14, 4)->default(0);
            $table->decimal('req_sc', 14, 4)->default(0);
            $table->string('uom', 16)->default('PCS');
            $table->text('remarks')->nullable();
            // OPEN, CLOSED.
            $table->string('status', 12)->default('OPEN');
            $table->timestamp('closed_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
            $table->index(['company_id', 'status', 'req_type']);
        });

        Schema::create('knit_cc_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 24);
            $table->date('program_date');
            $table->date('delivery_date')->nullable();
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('cpi', 24)->nullable();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('work_style_no', 60)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, SUBMITTED, APPROVED, IN_PRODUCTION, CLOSED, CANCELLED.
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
            $table->index(['company_id', 'status', 'program_date']);
        });

        Schema::create('knit_cc_program_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('knit_cc_programs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('item', 12);
            $table->string('description', 200)->nullable();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('size_desc', 80)->nullable();
            $table->string('gauge', 40)->nullable();
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 16, 4)->default(0);
            $table->string('uom', 16)->default('PCS');
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_cc_productions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requirement_id')->constrained('knit_cc_requirements')->cascadeOnDelete();
            $table->date('prod_date');
            // IN_HOUSE, SC_OUT_HOUSE.
            $table->string('source', 16);
            $table->foreignUlid('floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignUlid('machine_id')->nullable()->constrained('knit_machines')->nullOnDelete();
            $table->foreignUlid('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignUlid('operator_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignUlid('program_id')->nullable()->constrained('knit_cc_programs')->nullOnDelete();
            $table->decimal('qty', 14, 4);
            $table->decimal('reject_qty', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'requirement_id', 'prod_date']);
        });

        Schema::create('knit_cc_yarn_reqs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 24);
            // SOLID, YD.
            $table->string('kind', 8);
            $table->date('req_date');
            $table->string('knit_plan_no', 60)->nullable();
            $table->foreignUlid('program_id')->nullable()->constrained('knit_cc_programs')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('work_style_no', 60)->nullable();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('req_type', 48)->nullable();
            $table->text('remarks')->nullable();
            // DRAFT, SUBMITTED, APPROVED, PARTLY_ISSUED, ISSUED, CLOSED, CANCELLED.
            $table->string('status', 16)->default('DRAFT');
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no']);
            $table->index(['company_id', 'kind', 'status']);
        });

        Schema::create('knit_cc_yarn_req_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('req_id')->constrained('knit_cc_yarn_reqs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('requirement_id')->nullable()->constrained('knit_cc_requirements')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('fabric_type_id')->nullable()->constrained('fabric_types')->nullOnDelete();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->foreignUlid('composition_id')->constrained('fiber_compositions')->restrictOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('req_qty', 14, 4);
            $table->string('uom', 16)->default('KG');
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_cc_yarn_issues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('req_id')->constrained('knit_cc_yarn_reqs')->cascadeOnDelete();
            $table->foreignUlid('req_line_id')->constrained('knit_cc_yarn_req_lines')->cascadeOnDelete();
            // The requisition's number and the release's count on it: CYS-2026110001/2.
            $table->string('issue_no', 32);
            $table->date('issue_date');
            $table->foreignUlid('lot_id')->constrained('knit_yarn_lots')->restrictOnDelete();
            $table->decimal('qty', 14, 4);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'req_id']);
        });

        Schema::create('knit_cc_challans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            // BULK, SAMPLE.
            $table->string('kind', 8);
            // INTERNAL_TRANSFER, DELIVERY, RETURN, SC_RECEIVE.
            $table->string('challan_type', 20);
            $table->date('challan_date');
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->foreignUlid('from_floor_id')->nullable()->constrained('floors')->nullOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->text('remarks')->nullable();
            // DRAFT, ISSUED, RECEIVED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->date('rcv_date')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
            $table->index(['company_id', 'kind', 'status']);
        });

        Schema::create('knit_cc_challan_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('challan_id')->constrained('knit_cc_challans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('requirement_id')->nullable()->constrained('knit_cc_requirements')->nullOnDelete();
            $table->string('item', 12);
            $table->string('description', 200)->nullable();
            $table->foreignUlid('colour_id')->nullable()->constrained('colours')->nullOnDelete();
            $table->string('size_desc', 80)->nullable();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->decimal('qty', 14, 4);
            $table->string('uom', 16)->default('PCS');
            $table->string('remarks', 300)->nullable();
            // An S/C receive line names the programme part it books in against.
            $table->foreignUlid('program_line_id')->nullable()->constrained('knit_cc_program_lines')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knit_cc_challan_lines');
        Schema::dropIfExists('knit_cc_challans');
        Schema::dropIfExists('knit_cc_yarn_issues');
        Schema::dropIfExists('knit_cc_yarn_req_lines');
        Schema::dropIfExists('knit_cc_yarn_reqs');
        Schema::dropIfExists('knit_cc_productions');
        Schema::dropIfExists('knit_cc_program_lines');
        Schema::dropIfExists('knit_cc_programs');
        Schema::dropIfExists('knit_cc_requirements');
    }
};
