<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Knit Production → Production (AnnotechERP 15/04) → Yarn Twisting Entry:
 *
 * - `knit_twist_programs` + lines: a twisting job placed on a party (TWP-…),
 *   one line per yarn going out single and coming back twisted — Grey(Kg),
 *   TPI, ply and the rate. `received_qty` is what the receive challans have
 *   brought back against the line.
 * - `knit_twist_receives` + lines: twisted yarn coming back (TWR-…) — Grey(Kg)
 *   is what the line accounts for, Receive(Kg) what arrived; Finalized fixes
 *   the weights for the bill, Transfer marks the yarn taken onto the store.
 * - `knit_twist_bills` + lines: what the twister is owed (TWB-…), one line
 *   per finalised receive challan or an agreed adjustment; amount = qty × rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knit_twist_programs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 24);
            $table->date('program_date');
            $table->date('target_date')->nullable();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            // DRAFT, SUBMITTED, APPROVED, RUNNING, CLOSED, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
            $table->index(['company_id', 'status', 'program_date']);
        });

        Schema::create('knit_twist_program_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('program_id')->constrained('knit_twist_programs')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->string('colour_group', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('grey_qty', 14, 3);
            $table->decimal('tpi', 8, 2)->nullable();
            $table->unsignedSmallInteger('ply')->nullable();
            $table->decimal('rate', 14, 4)->nullable();
            $table->string('uom', 16);
            $table->decimal('received_qty', 14, 3)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_twist_receives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 24);
            $table->date('receive_date');
            $table->foreignUlid('program_id')->constrained('knit_twist_programs')->restrictOnDelete();
            $table->string('gate_pass_no', 40)->nullable();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->boolean('finalized')->default(false);
            $table->boolean('transferred')->default(false);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
            $table->index(['company_id', 'program_id']);
        });

        Schema::create('knit_twist_receive_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('receive_id')->constrained('knit_twist_receives')->cascadeOnDelete();
            $table->foreignUlid('program_line_id')->constrained('knit_twist_program_lines')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('colour_group', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->decimal('grey_qty', 14, 3);
            $table->decimal('receive_qty', 14, 3);
            $table->unsignedInteger('cones')->nullable();
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('knit_twist_bills', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('bill_no', 24);
            $table->date('bill_date');
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('program_id')->nullable()->constrained('knit_twist_programs')->nullOnDelete();
            $table->string('party_bill_no', 40)->nullable();
            // DRAFT, SUBMITTED, APPROVED, PAID, CANCELLED.
            $table->string('status', 12)->default('DRAFT');
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'bill_no']);
        });

        Schema::create('knit_twist_bill_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('bill_id')->constrained('knit_twist_bills')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->foreignUlid('receive_id')->nullable()->constrained('knit_twist_receives')->restrictOnDelete();
            $table->string('particulars', 200)->nullable();
            $table->foreignUlid('composition_id')->nullable()->constrained('fiber_compositions')->nullOnDelete();
            $table->foreignUlid('yarn_count_id')->nullable()->constrained('knit_yarn_counts')->nullOnDelete();
            $table->decimal('qty', 14, 3);
            $table->decimal('rate', 14, 4);
            $table->decimal('amount', 16, 2);
            $table->string('uom', 16);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knit_twist_bill_lines', 'knit_twist_bills', 'knit_twist_receive_lines', 'knit_twist_receives', 'knit_twist_program_lines', 'knit_twist_programs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
