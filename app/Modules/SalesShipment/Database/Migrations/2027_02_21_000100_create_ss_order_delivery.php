<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sales & Shipment → Order & Delivery (AnnotechERP 21/02), as Brickland keeps
 * it.
 *
 * - `ss_sales_orders` (SO-YYYYMMDD-####) with its item lines, the size bands
 *   under each line and the particulars carried on a line: a customer order,
 *   drafted, then submitted, checked, approved and confirmed; closed at the
 *   end. `version` counts the amendments applied to it.
 * - `ss_particulars`: the named attributes a line can carry (Carton
 *   Dimension, Poly Thickness, Print Placement …).
 * - `ss_sc_programmes` + lines (SC-YYYYMMDD-####): an order's work put out to
 *   a sub-contractor, the SC No the sub-contract registers are read by.
 * - `ss_amendments` + lines (SOA-2026-000001): size-wise revised quantity and
 *   rate and revised dates, approved, then applied onto the order.
 * - `ss_challans` + lines (DC-YYYYMMDD-####, gate pass GP-…): goods leaving
 *   the gate against a confirmed order, size by size.
 * - `ss_challan_returns` + lines (DCR-2026-000001): goods coming back on a
 *   delivered challan; the balance moves only once confirmed.
 * - `ss_bills` + lines (CB-YYYYMMDD-####): the check bill of delivered
 *   challan lines — verified, approved (finalized), paid, or rejected.
 * - `ss_month_closes`: the months locked against sales papers.
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

        Schema::create('ss_particulars', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->string('name', 80);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('ss_sales_orders', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('so_no', 24);
            $table->string('search_for', 16)->default('SALES_ORDER'); // PURCHASE_ORDER | SALES_ORDER
            $table->boolean('is_inter_company')->default(false);
            $table->boolean('is_embroidery')->default(false);
            $table->string('so_type', 16)->default('GENERAL'); // GENERAL | INTER_COMPANY | EMBROIDERY | SUB_CONTRACT
            $table->foreignUlid('customer_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->date('receive_date');
            $table->string('program_by', 120);
            $table->string('program_to', 120);
            $table->foreignUlid('bill_to_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->date('delivery_target_date');
            $table->foreignUlid('delivery_to_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->boolean('same_as_bill_to')->default(false);
            $table->integer('lead_time_days')->default(0);
            $table->text('delivery_address')->nullable();
            $table->string('payment_method', 12); // CASH | LC | CHEQUE | TT | CONSIGNMENT
            $table->foreignUlid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('conv_rate', 14, 4)->default(1);
            $table->string('payment_term', 40)->nullable();
            $table->string('mode_of_shipment', 16);
            $table->string('po_wo_no', 60);
            $table->text('others_term')->nullable();
            $table->text('pr_po_ref')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('additional_qty_pct', 6, 2)->default(0);
            $table->string('season', 40)->nullable();
            $table->string('department', 60)->nullable();
            $table->string('incoterm', 4)->nullable();
            $table->foreignUlid('destination_country_id')->nullable()->constrained('origin_countries')->nullOnDelete();
            $table->string('destination_port', 80)->nullable();
            $table->date('shipment_date');
            $table->foreignUlid('item_id')->nullable()->constrained('mp_items')->nullOnDelete();
            $table->boolean('is_poly_item')->default(false);
            $table->string('approval_status', 10)->default('DRAFT'); // DRAFT | SUBMITTED | CHECKED | APPROVED | CONFIRMED
            $table->string('state', 8)->default('OPEN'); // OPEN | CLOSED
            $table->unsignedSmallInteger('version')->default(0);
            $by($table, ['submitted', 'checked', 'approved', 'confirmed', 'closed']);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'so_no']);
        });

        Schema::create('ss_sales_order_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('order_id')->constrained('ss_sales_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('sl_no');
            $table->string('buyer', 160)->nullable();
            $table->string('order_ref', 120)->nullable();
            $table->string('style_ref', 120)->nullable();
            $table->string('wo_ref', 120)->nullable();
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('item_id')->constrained('mp_items')->restrictOnDelete();
            $table->string('gmt_color', 120)->nullable();
            $table->string('product_color', 120)->nullable();
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('uom', 16)->nullable();
            $table->string('specification', 300)->nullable();
            $table->string('code', 60)->nullable();
            $table->decimal('gross_weight', 14, 4)->nullable();
            $table->decimal('net_weight', 14, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('ss_sales_order_sizes', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('order_id')->constrained('ss_sales_orders')->cascadeOnDelete();
            $table->foreignUlid('line_id')->constrained('ss_sales_order_lines')->cascadeOnDelete();
            $table->string('size', 60);
            $table->decimal('pcs_per_size', 12, 4)->default(1);
            $table->decimal('qty', 16, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ss_sales_order_particulars', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('order_id')->constrained('ss_sales_orders')->cascadeOnDelete();
            $table->foreignUlid('line_id')->constrained('ss_sales_order_lines')->cascadeOnDelete();
            $table->foreignUlid('particular_id')->constrained('ss_particulars')->restrictOnDelete();
            $table->string('value', 200);
            $table->timestamps();
        });

        Schema::create('ss_sc_programmes', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->string('sc_no', 24);
            $table->foreignUlid('order_id')->constrained('ss_sales_orders')->restrictOnDelete();
            $table->foreignUlid('party_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->date('sc_date');
            $table->date('target_date');
            $table->string('process', 80);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'sc_no']);
        });

        Schema::create('ss_sc_programme_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('programme_id')->constrained('ss_sc_programmes')->cascadeOnDelete();
            $table->foreignUlid('size_id')->constrained('ss_sales_order_sizes')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('ss_amendments', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('amend_no', 24);
            $table->foreignUlid('order_id')->constrained('ss_sales_orders')->restrictOnDelete();
            $table->date('amend_date');
            $table->text('reason');
            $table->text('remarks')->nullable();
            $table->date('prev_target_date');
            $table->date('revised_target_date');
            $table->date('prev_shipment_date');
            $table->date('revised_shipment_date')->nullable();
            $table->unsignedSmallInteger('version'); // the order's version once applied
            $table->string('state', 10)->default('DRAFT'); // DRAFT | SUBMITTED | APPROVED | APPLIED | REJECTED | CANCELLED
            $table->json('snapshot')->nullable(); // the order as it stood when applied
            $by($table, ['submitted', 'approved', 'applied', 'rejected', 'cancelled']);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'amend_no']);
        });

        Schema::create('ss_amendment_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('amendment_id')->constrained('ss_amendments')->cascadeOnDelete();
            $table->foreignUlid('size_id')->constrained('ss_sales_order_sizes')->restrictOnDelete();
            $table->decimal('prev_qty', 16, 4);
            $table->decimal('revised_qty', 16, 4);
            $table->decimal('prev_rate', 14, 4);
            $table->decimal('revised_rate', 14, 4);
            $table->timestamps();
        });

        Schema::create('ss_challans', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('chalan_no', 24);
            $table->string('gate_pass_no', 24);
            $table->foreignUlid('order_id')->constrained('ss_sales_orders')->restrictOnDelete();
            $table->foreignUlid('customer_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->date('chalan_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->date('delivery_target_date');
            $table->string('mode_of_shipment', 16);
            $table->foreignUlid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('conv_rate', 14, 4)->default(1);
            $table->string('carrier_name', 120)->nullable();
            $table->string('carrier_no', 60)->nullable();
            $table->text('pr_po_ref')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_revised')->default(false);
            $table->string('state', 10)->default('DRAFT'); // DRAFT | SUBMITTED | DELIVERED | CANCELLED
            $by($table, ['submitted', 'delivered', 'cancelled']);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'chalan_no']);
        });

        Schema::create('ss_challan_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('challan_id')->constrained('ss_challans')->cascadeOnDelete();
            $table->foreignUlid('size_id')->constrained('ss_sales_order_sizes')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->string('remarks', 300)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ss_challan_returns', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('return_no', 24);
            $table->foreignUlid('challan_id')->constrained('ss_challans')->restrictOnDelete();
            $table->date('return_date');
            $table->string('reason', 24); // Quality Issue | Wrong Item | Excess Delivery | Buyer Rejection | Damaged in Transit | Other
            $table->text('remarks')->nullable();
            $table->string('state', 10)->default('DRAFT'); // DRAFT | CONFIRMED | CANCELLED
            $by($table, ['confirmed', 'cancelled']);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no']);
        });

        Schema::create('ss_challan_return_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('return_id')->constrained('ss_challan_returns')->cascadeOnDelete();
            $table->foreignUlid('challan_line_id')->constrained('ss_challan_lines')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->string('remarks', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('ss_bills', function (Blueprint $table) use ($base, $by): void {
            $base($table);
            $table->string('bill_no', 24);
            $table->foreignUlid('customer_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->decimal('loc_exchange_rate', 14, 4)->default(1);
            $table->date('bill_date');
            $table->decimal('deduction', 16, 4)->default(0);
            $table->text('remarks')->nullable();
            $table->string('state', 22)->default('DRAFT'); // DRAFT | WAITING_FOR_APPROVAL | VERIFIED | APPROVED | PAID | REJECTED
            $table->string('reject_reason', 300)->nullable();
            $by($table, ['submitted', 'verified', 'approved', 'paid', 'rejected']);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'bill_no']);
        });

        Schema::create('ss_bill_lines', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->foreignUlid('bill_id')->constrained('ss_bills')->cascadeOnDelete();
            $table->foreignUlid('challan_line_id')->constrained('ss_challan_lines')->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('exchange_rate', 14, 4)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ss_month_closes', function (Blueprint $table) use ($base): void {
            $base($table);
            $table->char('period', 7); // 2026-07
            $table->unsignedInteger('orders_count')->default(0);
            $table->text('remarks')->nullable();
            $table->json('exceptions')->nullable(); // what was still open when closed over it
            $table->foreignUlid('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
            $table->unique(['company_id', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['ss_month_closes', 'ss_bill_lines', 'ss_bills', 'ss_challan_return_lines', 'ss_challan_returns', 'ss_challan_lines', 'ss_challans', 'ss_amendment_lines', 'ss_amendments',
            'ss_sc_programme_lines', 'ss_sc_programmes', 'ss_sales_order_particulars', 'ss_sales_order_sizes', 'ss_sales_order_lines', 'ss_sales_orders', 'ss_particulars'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
