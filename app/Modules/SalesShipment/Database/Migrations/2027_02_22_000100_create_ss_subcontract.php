<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sales & Shipment → Sub-Contract (AnnotechERP 21/03), as Brickland keeps it:
 * the work an S/C programme (21/02, the "sales contract") puts out to a
 * sub-contractor, and what comes back.
 *
 * - `ss_sc_deliveries` + lines (SCD-YYYYMMDD-####, gate pass SGP-…): goods
 *   sent out to the party against the contract's lines; drafted, marked
 *   delivered, finalized (locked), or cancelled; closed by a reconciliation.
 * - `ss_sc_receipts` + lines (SCR-YYYYMMDD-####): what came back against a
 *   delivered chalan — finished goods, left-over or rejection — received and
 *   rejected quantity per line; finalized or cancelled.
 * - `ss_sc_returns` + lines (SCRT-2026-000001): received goods sent back to
 *   the party (rework, rejection return …); moves the balance once confirmed.
 * - `ss_sc_reconciliations` + lines (SCRC-2026-000001): a chalan squared —
 *   delivered, received, rejected and returned read off it, short agreed,
 *   balance worked out; reconciled to close the chalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $base = function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
        };
        $by = function (Blueprint $table, array $steps): void {
            foreach ($steps as $s) {
                $table->timestamp("{$s}_at")->nullable();
                $table->foreignUlid("{$s}_by")->nullable()->constrained('users')->nullOnDelete();
            }
        };

        Schema::create('ss_sc_deliveries', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('chalan_no', 24);
            $table->string('gate_pass_no', 24);
            $table->foreignUlid('programme_id')->constrained('ss_sc_programmes')->restrictOnDelete();
            $table->foreignUlid('customer_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('transfer_from_id')->nullable()->constrained('mp_suppliers')->nullOnDelete();
            $table->date('chalan_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('delivery_target_date');
            $table->string('mode_of_shipment', 16);
            $table->string('carrier_name', 120)->nullable();
            $table->string('carrier_no', 60)->nullable();
            $table->text('pr_po_ref')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_revised')->default(false);
            $table->string('state', 10)->default('DRAFT'); // DRAFT | DELIVERED | CANCELLED
            $table->boolean('finalized')->default(false);
            $table->boolean('closed')->default(false); // reconciled
            $by($table, ['delivered', 'finalized', 'cancelled']);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'chalan_no']);
        });

        Schema::create('ss_sc_delivery_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('delivery_id')->constrained('ss_sc_deliveries')->cascadeOnDelete();
            $table->foreignUlid('programme_line_id')->constrained('ss_sc_programme_lines')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ss_sc_receipts', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('mrr_no', 24);
            $table->foreignUlid('delivery_id')->constrained('ss_sc_deliveries')->restrictOnDelete();
            $table->date('mrr_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('customer_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->string('receive_type', 16); // FINISHED_GOODS | LEFT_OVER | REJECTION
            $table->text('remarks')->nullable();
            $table->boolean('is_revised')->default(false);
            $table->string('state', 10)->default('RECEIVED'); // RECEIVED | CANCELLED
            $table->boolean('finalized')->default(false);
            $by($table, ['finalized', 'cancelled']);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'mrr_no']);
        });

        Schema::create('ss_sc_receipt_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('receipt_id')->constrained('ss_sc_receipts')->cascadeOnDelete();
            $table->foreignUlid('delivery_line_id')->constrained('ss_sc_delivery_lines')->restrictOnDelete();
            $table->decimal('qty', 16, 4)->default(0);
            $table->decimal('rejected_qty', 16, 4)->default(0);
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ss_sc_returns', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('return_no', 24);
            $table->foreignUlid('receipt_id')->constrained('ss_sc_receipts')->restrictOnDelete();
            $table->date('return_date');
            $table->string('reason', 20); // Rework | Rejection Return | Wrong Delivery | Other
            $table->text('remarks')->nullable();
            $table->string('state', 10)->default('DRAFT'); // DRAFT | CONFIRMED | CANCELLED
            $by($table, ['confirmed', 'cancelled']);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no']);
        });

        Schema::create('ss_sc_return_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('return_id')->constrained('ss_sc_returns')->cascadeOnDelete();
            $table->foreignUlid('receipt_line_id')->constrained('ss_sc_receipt_lines')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('ss_sc_reconciliations', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('recon_no', 24);
            $table->foreignUlid('delivery_id')->constrained('ss_sc_deliveries')->restrictOnDelete();
            $table->date('recon_date');
            $table->text('remarks')->nullable();
            $table->string('state', 10)->default('PENDING'); // PENDING | MATCHED | SHORT | EXCESS | RECONCILED
            $by($table, ['reconciled', 'reopened']);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'recon_no']);
        });

        Schema::create('ss_sc_reconciliation_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('reconciliation_id')->constrained('ss_sc_reconciliations')->cascadeOnDelete();
            $table->foreignUlid('delivery_line_id')->constrained('ss_sc_delivery_lines')->restrictOnDelete();
            $table->decimal('delivered', 16, 4)->default(0);
            $table->decimal('received', 16, 4)->default(0);
            $table->decimal('rejected', 16, 4)->default(0);
            $table->decimal('returned', 16, 4)->default(0);
            $table->decimal('short_qty', 16, 4)->default(0);
            $table->decimal('balance', 16, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ss_sc_reconciliation_lines', 'ss_sc_reconciliations', 'ss_sc_return_lines', 'ss_sc_returns', 'ss_sc_receipt_lines', 'ss_sc_receipts', 'ss_sc_delivery_lines', 'ss_sc_deliveries'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
