<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trade & Export → Import (AnnotechERP 22/03), and the two declarations of
 * Operations (22/02) that wait for it:
 *
 * - `tx_import_pis` + lines: the supplier's proforma invoice, built from its
 *   accessory, fabric and yarn purchase orders (IPI-2026-000001 tracking).
 * - `tx_import_lcs` + commodities / PIs: the back-to-back L/C opened on an
 *   export L/C.
 * - `tx_bills_of_entry` + lines: the customs entry, line by line from the
 *   goods received against the L/C's PIs.
 * - `tx_import_acceptances`: the accepted import bill (IFDBC), one bill of
 *   exchange number each.
 * - `tx_uds` + `tx_ud_lcs`: the utilisation declaration over B2B L/Cs.
 * - `tx_ud_transfers`: entitlement moved between two declarations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $base = function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
        };
        $by = fn (Blueprint $table) => $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
        $branch = fn (Blueprint $table, string $column) => $table->foreignUlid($column)->nullable()->constrained('tx_bank_branches')->nullOnDelete();
        $country = fn (Blueprint $table, string $column) => $table->foreignUlid($column)->nullable()->constrained('origin_countries')->nullOnDelete();

        Schema::create('tx_import_pis', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('tracking_no', 24);
            $table->string('pi_no', 60);
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('export_lc_id')->nullable()->constrained('tx_export_lcs')->nullOnDelete();
            $table->date('pi_date');
            $table->decimal('pi_amount', 16, 4);
            $table->decimal('net_amount', 16, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->string('status', 10)->default('DRAFT'); // DRAFT | APPROVED
            $table->timestamp('approved_at')->nullable();
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'supplier_id', 'pi_no']);
            $table->unique(['company_id', 'tracking_no']);
        });

        Schema::create('tx_import_pi_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('pi_id')->constrained('tx_import_pis')->cascadeOnDelete();
            $table->string('po_kind', 5)->nullable(); // ACC | FAB | YARN; empty on a line typed by hand
            $table->ulid('po_line_id')->nullable();
            $table->string('po_no', 60)->nullable();
            $table->string('item', 300);
            $table->decimal('qty', 16, 4);
            $table->string('uom', 16);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('amount', 16, 4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['po_kind', 'po_line_id']);
        });

        Schema::create('tx_import_lcs', function (Blueprint $table) use ($base, $by, $branch, $country): void {
            $base($table);
            $table->string('ref_no', 60);
            $table->string('lc_type', 24);
            $table->boolean('is_cash')->default(false);
            $table->string('commodity_type', 16);
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->foreignUlid('export_lc_id')->nullable()->constrained('tx_export_lcs')->restrictOnDelete();
            $branch($table, 'sender_branch_id');
            $branch($table, 'receiving_branch_id');
            $branch($table, 'lean_branch_id');
            $table->boolean('same_receiver_bank')->default(false);
            $table->date('issue_date');
            $table->date('expire_date');
            $table->date('ref_date')->nullable();
            $table->string('currency', 3);
            $table->string('inco_term', 24);
            $table->string('delivery_place', 24)->nullable();
            $table->text('payment_term_description')->nullable();
            $table->string('lcaf_no', 60)->nullable();
            $table->string('insurance', 160)->nullable();
            $table->decimal('credit_tolerance_pct', 6, 2)->default(5);
            $table->boolean('partial_shipment')->default(false);
            $table->boolean('trans_shipment')->default(false);
            $table->string('btma_cert_no', 60)->nullable();
            $table->date('btma_cert_date')->nullable();
            $table->string('port_of_loading', 24);
            $table->string('port_of_discharge', 24);
            $table->date('last_shipment_date');
            $table->string('time_of_shipment', 80)->nullable();
            $country($table, 'country_of_origin_id');
            $table->string('bank_ref_no', 60)->nullable();
            $table->string('lc_bank_code', 12)->nullable();
            $table->string('lc_year', 6)->nullable();
            $table->string('lc_code', 12)->nullable();
            $table->string('lc_sl_no', 12)->nullable();
            $country($table, 'place_of_expiry_id');
            $country($table, 'place_of_issue_id');
            $table->decimal('lc_value', 16, 4);
            $table->string('payment_term', 24);
            $table->unsignedInteger('tenor_days')->default(0);
            $table->string('irc_no', 60)->nullable();
            $table->string('shipment_mode', 24)->nullable();
            $table->decimal('freight_cost', 16, 4)->nullable();
            $table->string('lc_status', 10)->default('APPROVED'); // APPROVED | APPLIED | AMENDED | CLOSED
            $table->string('bin_no', 60)->nullable();
            $table->string('status', 10)->default('DRAFT'); // DRAFT | SUBMITTED | COMPLETE
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'ref_no']);
        });

        Schema::create('tx_import_lc_commodities', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('lc_id')->constrained('tx_import_lcs')->cascadeOnDelete();
            $table->string('name', 160);
            $table->decimal('qty', 16, 4);
            $table->string('unit', 16);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tx_import_lc_pis', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('lc_id')->constrained('tx_import_lcs')->cascadeOnDelete();
            $table->foreignUlid('pi_id')->constrained('tx_import_pis')->restrictOnDelete();
            $table->timestamps();
            $table->unique('pi_id');
        });

        Schema::create('tx_bills_of_entry', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('be_no', 60);
            $table->date('be_date');
            $table->foreignUlid('lc_id')->constrained('tx_import_lcs')->restrictOnDelete();
            $table->foreignUlid('entry_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('custom_code', 12)->nullable();
            $table->string('custom_name', 120)->nullable();
            $table->decimal('bill_amount', 16, 4);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 14, 4);
            $table->string('local_currency', 3)->default('BDT');
            $table->decimal('equivalent_amount', 16, 4);
            $table->text('remarks')->nullable();
            $table->ulid('file_id')->nullable();
            $table->string('file_name', 200)->nullable();
            $table->boolean('final')->default(false);
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'be_no']);
        });

        Schema::create('tx_bill_of_entry_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('be_id')->constrained('tx_bills_of_entry')->cascadeOnDelete();
            $table->foreignUlid('pi_line_id')->constrained('tx_import_pi_lines')->restrictOnDelete();
            $table->string('mrr_no', 40);
            $table->string('challan_no', 80)->nullable();
            $table->decimal('receive_qty', 16, 4);
            $table->decimal('receive_amount', 16, 4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['pi_line_id', 'mrr_no']);
        });

        Schema::create('tx_import_acceptances', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('boe_no', 24);
            $table->foreignUlid('lc_id')->constrained('tx_import_lcs')->restrictOnDelete();
            $table->decimal('invoiced_amount', 16, 4);
            $table->string('currency', 3);
            $table->date('acceptance_date');
            $table->string('ifdbc_no', 60);
            $table->decimal('ifdbc_amount', 16, 4)->default(0);
            $table->boolean('is_pending')->default(false);
            $table->date('open_date');
            $table->date('maturity_date');
            $table->date('ext_maturity_date')->nullable();
            $table->decimal('b2b_qty', 16, 4)->default(0);
            $table->string('attached_document', 200)->nullable();
            $table->string('payment_type', 4)->default('NA'); // NA | PMT | TT | ADJ
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->string('local_currency', 3)->default('BDT');
            $table->text('remarks')->nullable();
            $table->decimal('paid_amount', 16, 4)->default(0);
            $table->date('paid_date')->nullable();
            $table->boolean('accounting_done')->default(false);
            $table->boolean('final')->default(false);
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'boe_no']);
        });

        Schema::create('tx_uds', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('ud_no', 60);
            $table->date('ud_date');
            $table->string('authority', 10);
            $table->unsignedInteger('amendment_no')->default(0);
            $table->boolean('final')->default(false);
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'ud_no']);
        });

        Schema::create('tx_ud_lcs', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('ud_id')->constrained('tx_uds')->cascadeOnDelete();
            $table->foreignUlid('import_lc_id')->constrained('tx_import_lcs')->restrictOnDelete();
            $table->timestamps();
            $table->unique('import_lc_id');
        });

        Schema::create('tx_ud_transfers', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('material', 16);
            $table->foreignUlid('from_ud_id')->constrained('tx_uds')->restrictOnDelete();
            $table->foreignUlid('to_ud_id')->constrained('tx_uds')->restrictOnDelete();
            $table->decimal('receive_qty', 16, 4);
            $table->decimal('export_qty', 16, 4)->default(0);
            $table->decimal('loss_pct', 6, 2)->default(0);
            $table->decimal('loss_qty', 16, 4)->default(0);
            $table->decimal('balance_qty', 16, 4)->default(0);
            $table->decimal('transfer_qty', 16, 4);
            $table->string('status', 10)->default('DRAFT'); // DRAFT | APPROVED
            $by($table);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['tx_ud_transfers', 'tx_ud_lcs', 'tx_uds', 'tx_import_acceptances', 'tx_bill_of_entry_lines', 'tx_bills_of_entry', 'tx_import_lc_pis', 'tx_import_lc_commodities', 'tx_import_lcs', 'tx_import_pi_lines', 'tx_import_pis'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
