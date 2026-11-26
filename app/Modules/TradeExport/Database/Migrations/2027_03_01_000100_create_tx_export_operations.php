<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trade & Export → Operations (AnnotechERP 22/02), the export paper chain:
 *
 * - `tx_export_pis` + lines: the advance proforma invoice on a buyer.
 * - `tx_export_lcs` + `tx_export_lc_pis` / `tx_export_lc_orders`: the export
 *   L/C or sales contract, the PIs and orders it is opened against.
 * - `tx_commercial_invoices` + lines: the shipping invoice on an L/C (prefix
 *   + auto number), its orders with a colour / size breakdown.
 * - `tx_export_bills` + invoices: the customs export bill lodged for invoices.
 * - `tx_bills_of_exchange` + invoices / distribution: the bill negotiated at
 *   the bank (FDBP / FDBC / DA / DP), or an advance against export (IP).
 * - `tx_bill_realisations` + distribution: early and final realisation.
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
        $applicant = fn (Blueprint $table) => $table->foreignUlid('applicant_id')->nullable()->constrained('tx_lc_applicants')->nullOnDelete();

        Schema::create('tx_export_pis', function (Blueprint $table) use ($base, $by, $branch): void {
            $base($table);
            $table->string('pi_no', 60);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->string('buyer_attention', 160);
            $table->date('pi_date');
            $table->decimal('pi_amount', 16, 4);
            $table->string('currency', 3);
            $table->string('commodity_type', 24)->nullable();
            $table->string('inco_term', 24)->nullable();
            $table->string('delivery_place', 24)->nullable();
            $table->string('payment_term', 24)->nullable();
            $table->date('expected_delivery_date')->nullable();
            $branch($table, 'bank_branch_id');
            $table->foreignUlid('origin_country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->string('packing', 160)->nullable();
            $table->string('shipment_mode', 24)->nullable();
            $table->string('transport_mode', 16)->nullable();
            $table->boolean('partial_shipment')->default(false);
            $table->boolean('transhipment')->default(false);
            $table->date('last_shipment_date')->nullable();
            $table->text('shipment_schedule')->nullable();
            $table->unsignedInteger('validity_days')->nullable();
            $table->string('place_of_signature', 120)->nullable();
            $table->decimal('tolerance_pct', 6, 2)->nullable();
            $table->string('tolerance_description', 200)->nullable();
            $table->text('payment_way')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->string('port_of_loading', 24)->nullable();
            $table->string('port_of_discharge', 120)->nullable();
            $table->text('insurance')->nullable();
            $table->text('shipment_terms')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('total_amount', 16, 4)->default(0);
            $table->decimal('advance_pct', 6, 2)->default(0);
            $table->decimal('advance_amount', 16, 4)->default(0);
            $table->string('discount_type', 8)->default('AMOUNT'); // PERCENT | AMOUNT
            $table->decimal('discount_value', 16, 4)->default(0);
            $table->decimal('discount_amount', 16, 4)->default(0);
            $table->decimal('net_amount', 16, 4)->default(0);
            $table->string('status', 10)->default('DRAFT'); // DRAFT | SUBMITTED | COMPLETE | CANCELLED
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'pi_no']);
        });

        Schema::create('tx_export_pi_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('pi_id')->constrained('tx_export_pis')->cascadeOnDelete();
            $table->string('item_group', 40);
            $table->string('item_type', 80)->nullable();
            $table->string('description', 300);
            $table->string('sku', 60)->nullable();
            $table->string('color', 120)->nullable();
            $table->string('size', 60)->nullable();
            $table->decimal('qty', 16, 4);
            $table->string('uom', 16);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('total_price', 16, 4);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tx_export_lcs', function (Blueprint $table) use ($base, $by, $branch, $applicant): void {
            $base($table);
            $table->string('kind', 2); // LC | SC
            $table->string('lc_no', 60);
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $table->date('issue_date');
            $table->date('expiry_date');
            $table->foreignUlid('place_of_expiry_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->string('currency', 3);
            $branch($table, 'sender_branch_id');
            $branch($table, 'receiving_branch_id');
            $branch($table, 'advising_branch_id');
            $table->boolean('same_receiver_bank')->default(false);
            $table->string('inco_term', 24);
            $table->string('delivery_place', 24)->nullable();
            $table->text('payment_term_description')->nullable();
            $table->decimal('credit_tolerance_pct', 6, 2)->default(3);
            $table->string('port_of_loading', 24);
            $table->string('port_of_discharge', 120);
            $table->boolean('partial_shipment')->default(false);
            $table->boolean('trans_shipment')->default(false);
            $table->date('last_shipment_date');
            $table->string('notify_party', 200);
            $table->boolean('other_info')->default(false);
            $table->boolean('is_advance')->default(false);
            $applicant($table);
            $table->decimal('lc_amount', 16, 4)->default(0);
            $table->string('payment_term', 24);
            $table->unsignedInteger('payment_days')->default(0);
            $table->decimal('bb_limit_pct', 6, 2)->nullable();
            $table->string('shipment_mode', 24)->nullable();
            $table->decimal('lc_value', 16, 4);
            $table->decimal('po_value', 16, 4)->default(0);
            $table->boolean('ignore_difference')->default(false);
            $table->decimal('lc_charge', 16, 4)->nullable();
            $table->string('merge_state', 8)->default('NA'); // NA | MARGED | REPLACED
            $table->unsignedInteger('amendment_no')->default(0);
            $table->string('status', 10)->default('DRAFT'); // DRAFT | SUBMITTED | COMPLETE
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'lc_no']);
        });

        Schema::create('tx_export_lc_pis', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('lc_id')->constrained('tx_export_lcs')->cascadeOnDelete();
            $table->foreignUlid('pi_id')->constrained('tx_export_pis')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['lc_id', 'pi_id']);
        });

        Schema::create('tx_export_lc_orders', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('lc_id')->constrained('tx_export_lcs')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->decimal('unit_price', 14, 4);
            $table->timestamps();
            $table->unique(['lc_id', 'order_id']);
        });

        Schema::create('tx_commercial_invoices', function (Blueprint $table) use ($base, $by, $applicant): void {
            $base($table);
            $table->foreignUlid('lc_id')->constrained('tx_export_lcs')->restrictOnDelete();
            $table->string('invoice_prefix', 40);
            $table->unsignedInteger('auto_no');
            $table->string('invoice_no', 80);
            $table->date('invoice_date');
            $table->string('currency', 3);
            $table->decimal('loc_exchange_rate', 14, 4)->nullable();
            $table->string('exp_no', 60);
            $table->unsignedTinyInteger('exp_part_no')->default(3);
            $table->date('exp_date');
            $table->string('forwarder', 24)->nullable();
            $table->date('ex_factory_date');
            $table->date('on_board_date')->nullable();
            $table->string('bl_no', 60)->nullable();
            $table->date('bl_date')->nullable();
            $table->date('handover_date');
            $table->string('payment_term', 24);
            $table->unsignedInteger('payment_days')->default(0);
            $table->string('port_of_loading', 24);
            $table->string('port_of_discharge', 24);
            $table->foreignUlid('delivery_country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->date('target_collection_date')->nullable();
            $table->string('shipment_mode', 24);
            $applicant($table);
            $table->decimal('invoiced_amount', 16, 4)->default(0);
            $table->decimal('penalty_amount', 16, 4)->default(0);
            $table->decimal('agency_commission', 16, 4)->default(0);
            $table->decimal('sales_discount', 16, 4)->default(0);
            $table->decimal('net_amount', 16, 4)->default(0);
            $table->string('freight_type', 8)->nullable();
            $table->decimal('freight_charges', 16, 4)->default(0);
            $table->boolean('adjust_against_advance')->default(false);
            $table->boolean('is_negotiated')->default(false);
            $table->decimal('export_percentage', 6, 2)->nullable();
            $table->string('vessel_no', 60)->nullable();
            $table->string('exp_reg_no', 60)->nullable();
            $table->date('exp_reg_date')->nullable();
            $table->string('marks_and_nos', 200)->nullable();
            $table->string('pre_carriage_by', 120)->nullable();
            $table->string('container_size', 8)->nullable();
            $table->string('container_no', 200)->nullable();
            $table->string('exp_inv_rex', 60)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('final')->default(false);
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'invoice_no']);
            $table->index(['company_id', 'invoice_prefix']);
        });

        Schema::create('tx_commercial_invoice_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('invoice_id')->constrained('tx_commercial_invoices')->cascadeOnDelete();
            $table->foreignUlid('order_id')->constrained('merch_orders')->restrictOnDelete();
            $table->string('pattern', 80)->nullable();
            $table->string('s_all', 40)->nullable();
            $table->string('hs_code', 24)->nullable();
            $table->string('hs_category', 80)->nullable();
            $table->string('description', 300)->nullable();
            $table->string('unit', 16);
            $table->decimal('inv_qty', 16, 4);
            $table->json('size_breakdown')->nullable();
            $table->decimal('unit_price', 14, 4);
            $table->decimal('amount', 16, 4);
            $table->unsignedInteger('ctn_qty')->default(0);
            $table->decimal('net_weight', 14, 3)->default(0);
            $table->decimal('gross_weight', 14, 3)->default(0);
            $table->decimal('cbm', 12, 4)->default(0);
            $table->string('ctn_measurement', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tx_export_bills', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('bill_no', 60);
            $table->date('bill_date');
            $table->decimal('charge', 16, 4)->default(0);
            $table->string('pass_book_page', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->ulid('file_id')->nullable();
            $table->string('file_name', 200)->nullable();
            $table->boolean('final')->default(false);
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'bill_no']);
        });

        Schema::create('tx_export_bill_invoices', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('bill_id')->constrained('tx_export_bills')->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained('tx_commercial_invoices')->restrictOnDelete();
            $table->timestamps();
            $table->unique('invoice_id');
        });

        Schema::create('tx_bills_of_exchange', function (Blueprint $table) use ($base, $by, $branch, $applicant): void {
            $base($table);
            $table->string('bill_no', 60);
            $table->boolean('is_advance')->default(false);
            $table->foreignUlid('lc_id')->nullable()->constrained('tx_export_lcs')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->constrained('buyers')->restrictOnDelete();
            $applicant($table);
            $table->date('bill_date');
            $table->decimal('bill_amount', 16, 4);
            $table->string('currency', 3);
            $table->decimal('local_rate', 14, 4);
            $table->string('local_currency', 3)->default('BDT');
            $branch($table, 'issuing_branch_id');
            $branch($table, 'advising_branch_id');
            $branch($table, 'doc_credit_branch_id');
            $branch($table, 'receiving_branch_id');
            $table->boolean('same_receiver_bank')->default(false);
            $table->string('payment_term', 24)->nullable();
            $table->text('remarks')->nullable();
            $table->string('bank_bill_type', 4); // IP | FDBP | FDBC | DA | DP
            $table->string('bank_bill_ref_no', 60)->nullable();
            $table->string('remittance_ref_no', 60)->nullable();
            $table->date('due_date');
            $table->date('ref_date')->nullable();
            $table->string('link_type', 2)->nullable(); // PI | PO (an advance)
            $table->foreignUlid('pi_id')->nullable()->constrained('tx_export_pis')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->boolean('final')->default(false);
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'bill_no']);
        });

        Schema::create('tx_boe_invoices', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('boe_id')->constrained('tx_bills_of_exchange')->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained('tx_commercial_invoices')->restrictOnDelete();
            $table->timestamps();
            $table->unique('invoice_id');
        });

        Schema::create('tx_bill_realisations', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('type', 5); // EARLY | FINAL
            $table->string('realized_no', 60);
            $table->foreignUlid('boe_id')->constrained('tx_bills_of_exchange')->restrictOnDelete();
            $table->date('realized_date');
            $table->string('bank_bill_type', 4);
            $table->string('bank_name', 160);
            $table->string('export_currency', 3);
            $table->string('local_currency', 3);
            $table->decimal('rate_ods', 14, 4);
            $table->decimal('rate_ttc', 14, 4)->nullable();
            $table->decimal('invoice_amount', 16, 4);
            $table->decimal('invoice_amount_bdt', 16, 4);
            $table->decimal('collection_amount', 16, 4)->default(0);
            $table->decimal('fbc_amount', 16, 4)->default(0);
            $table->decimal('penalty_amount', 16, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->boolean('final')->default(false);
            $by($table);
            $table->timestamps();
            $table->unique(['company_id', 'realized_no']);
        });

        // The accounting split of an advance bill or a realisation; one table, told apart by owner.
        Schema::create('tx_bill_distributions', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->string('owner_type', 12); // BOE | REALISATION
            $table->ulid('owner_id');
            $table->foreignUlid('account_id')->constrained('acc_accounts')->restrictOnDelete();
            $table->string('dr_cr', 2); // DR | CR
            $table->string('currency', 3);
            $table->decimal('amount', 16, 4);
            $table->string('note', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        foreach (['tx_bill_distributions', 'tx_bill_realisations', 'tx_boe_invoices', 'tx_bills_of_exchange', 'tx_export_bill_invoices', 'tx_export_bills', 'tx_commercial_invoice_lines', 'tx_commercial_invoices', 'tx_export_lc_orders', 'tx_export_lc_pis', 'tx_export_lcs', 'tx_export_pi_lines', 'tx_export_pis'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
