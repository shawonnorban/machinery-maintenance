<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Gate Pass (AnnotechERP 11/02–11/04): the pass itself — a Main Gate Pass for
 * an employee or an Item Gate Pass for goods, OUT & IN (returnable) or OUT
 * ONLY — prepared, approved, let out at the gate, and (when returnable)
 * brought back in one or more Gate IN entries, its short quantity closed by
 * an approver; every step on its own audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_passes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('gp_no', 24);
            $table->date('gp_date');
            $table->foreignUlid('category_id')->constrained('gate_pass_categories')->restrictOnDelete();
            // MAIN (an employee) or ITEM (goods) — the category's kind when the pass was made.
            $table->string('kind', 8);
            // RETURNABLE (OUT & IN) or NON_RETURNABLE (OUT ONLY).
            $table->string('pass_type', 16);
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('style', 120)->nullable();
            $table->string('po_no', 120)->nullable();
            $table->foreignUlid('purpose_id')->nullable()->constrained('gate_pass_purposes')->restrictOnDelete();
            $table->foreignUlid('location_id')->nullable()->constrained('gate_pass_locations')->restrictOnDelete();
            // A Main pass's destination when it is not in the list.
            $table->string('destination_text', 200)->nullable();
            $table->date('expected_return')->nullable();
            // EMPLOYEE or NON_EMPLOYEE.
            $table->string('carrier_type', 16);
            $table->foreignUlid('employee_id')->nullable()->constrained('hr_employees')->restrictOnDelete();
            $table->string('carrier_name', 120)->nullable();
            $table->string('carrier_company', 160)->nullable();
            $table->string('carrier_mobile', 32)->nullable();
            $table->string('carrier_nid', 40)->nullable();
            $table->string('vehicle_no', 60)->nullable();
            $table->string('carrier_remarks', 500)->nullable();
            $table->text('remarks')->nullable();
            $table->text('documents')->nullable();
            // DRAFT, PENDING, APPROVED, REJECTED, CANCELLED, OUT, PARTIAL, SHORT, CLOSED or COMPLETED.
            $table->string('status', 10)->default('DRAFT');
            $table->foreignUlid('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('out_at')->nullable();
            $table->foreignUlid('out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('out_remarks', 500)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'gp_no']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('gate_pass_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gate_pass_id')->constrained('gate_passes')->cascadeOnDelete();
            $table->foreignUlid('item_id')->constrained('gate_pass_items')->restrictOnDelete();
            $table->string('unit', 8);
            $table->decimal('quantity', 14, 3);
            // Declared at a final return: what will not come back, and why.
            $table->decimal('short_qty', 14, 3)->default(0);
            $table->string('short_reason', 16)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('gate_pass_ins', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gate_pass_id')->constrained('gate_passes')->cascadeOnDelete();
            $table->string('gpi_no', 24);
            $table->timestamp('in_at');
            // Nothing more is coming back: what is still out is declared short.
            $table->boolean('final')->default(false);
            $table->string('short_reason', 16)->nullable();
            $table->string('remarks', 500)->nullable();
            $table->foreignUlid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'gpi_no']);
        });

        Schema::create('gate_pass_in_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gate_pass_in_id')->constrained('gate_pass_ins')->cascadeOnDelete();
            $table->foreignUlid('gate_pass_line_id')->constrained('gate_pass_lines')->cascadeOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->timestamps();
        });

        Schema::create('gate_pass_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('gate_pass_id')->constrained('gate_passes')->cascadeOnDelete();
            // CREATED, SUBMITTED, APPROVED, REJECTED, SENT_BACK, CANCELLED, OUT, IN, CLOSED.
            $table->string('action', 12);
            $table->string('to_status', 10);
            $table->string('note', 2000)->nullable();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['company_id', 'gate_pass_id']);
        });
    }

    public function down(): void
    {
        foreach (['gate_pass_events', 'gate_pass_in_lines', 'gate_pass_ins', 'gate_pass_lines', 'gate_passes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
