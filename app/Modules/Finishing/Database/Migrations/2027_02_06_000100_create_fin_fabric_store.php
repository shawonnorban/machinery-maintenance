<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Finishing Line → Fabric Store (AnnotechERP 18/05): passed batches handed
 * to the finished fabric store on a delivery note (one order per note),
 * which the store receives into lots; every lot's balance is the sum of its
 * moves — received, issued to cutting, returned, transferred away. Each roll
 * of a lot is racked and barcoded; a cutting floor's requisition records the
 * ask and what the store released against it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_store_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('delivery_no', 24);
            $table->date('receive_date');
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->string('status', 10)->default('DRAFT'); // DRAFT | SENT | RECEIVED
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignUlid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'delivery_no'], 'fin_store_deliveries_no_unique');
        });

        Schema::create('fin_store_delivery_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('delivery_id')->constrained('fin_store_deliveries')->cascadeOnDelete();
            $table->foreignUlid('receipt_id')->constrained('fin_batch_receipts')->restrictOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->string('fabric_type', 40)->nullable();
            $table->string('colour', 120)->nullable();
            $table->string('fabric_details')->nullable();
            $table->string('item_code', 40)->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->string('tag_batch_no', 40)->nullable();
            $table->string('shade_no', 40)->nullable();
            $table->string('dia_type', 8)->nullable();
            $table->decimal('fin_gsm', 8, 2)->nullable();
            $table->decimal('fin_dia', 8, 2)->nullable();
            $table->decimal('grey_qty', 14, 3);
            $table->decimal('finish_qty', 14, 3);
            $table->unsignedInteger('rolls')->default(0);
            $table->decimal('rate', 12, 2)->nullable();
            $table->boolean('aop')->default(false);
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fin_store_lots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('lot_code', 24);
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('delivery_line_id')->constrained('fin_store_delivery_lines')->restrictOnDelete();
            $table->foreignUlid('receipt_id')->constrained('fin_batch_receipts')->restrictOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('style', 120)->nullable();
            $table->string('colour', 120)->nullable();
            $table->string('fabric_type', 40)->nullable();
            $table->string('fabric_details')->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->string('shade_no', 40)->nullable();
            $table->string('dia_type', 8)->nullable();
            $table->decimal('fin_gsm', 8, 2)->nullable();
            $table->decimal('fin_dia', 8, 2)->nullable();
            $table->decimal('received_kg', 14, 3);
            $table->unsignedInteger('rolls')->default(0);
            $table->decimal('rate', 12, 2)->nullable();
            $table->date('received_on');
            $table->timestamps();
            $table->unique(['company_id', 'lot_code'], 'fin_store_lots_code_unique');
            $table->unique('delivery_line_id');
        });

        Schema::create('fin_store_moves', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('fin_store_lots')->cascadeOnDelete();
            $table->string('kind', 10); // RECEIPT | ISSUE | RETURN | TRANSFER
            $table->decimal('qty_kg', 14, 3); // signed
            $table->integer('rolls')->default(0); // signed
            $table->date('move_date');
            $table->string('doc_type', 12); // DELIVERY | REQUISITION | RETURN
            $table->ulid('doc_id');
            $table->string('doc_no', 40);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['lot_id', 'move_date']);
            $table->index(['doc_type', 'doc_id']);
        });

        Schema::create('fin_store_rolls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('fin_store_lots')->cascadeOnDelete();
            $table->string('roll_no', 40);
            $table->string('barcode', 24);
            $table->decimal('weight_kg', 12, 3);
            $table->string('rack', 24)->nullable();
            $table->string('bin', 24)->nullable();
            $table->string('status', 10)->default('IN_STORE'); // IN_STORE | ISSUED
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'barcode'], 'fin_store_rolls_barcode_unique');
            $table->unique(['lot_id', 'roll_no'], 'fin_store_rolls_roll_unique');
        });

        Schema::create('fin_cut_requisitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('req_no', 40);
            $table->date('req_date');
            $table->string('cut_prog_ref', 40)->nullable();
            $table->foreignUlid('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('style', 120)->nullable();
            $table->string('colour', 120)->nullable();
            $table->string('batch_no', 40)->nullable();
            $table->string('action', 120)->nullable();
            $table->string('status', 10); // DRAFT | PARTIAL | COMPLETE | CANCELLED
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'req_no'], 'fin_cut_requisitions_no_unique');
        });

        Schema::create('fin_cut_requisition_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('requisition_id')->constrained('fin_cut_requisitions')->cascadeOnDelete();
            $table->foreignUlid('lot_id')->constrained('fin_store_lots')->restrictOnDelete();
            $table->string('description')->nullable();
            $table->string('lot_no', 40)->nullable();
            $table->string('batch_no', 40)->nullable();
            $table->decimal('req_qty', 14, 3);
            $table->decimal('issue_qty', 14, 3)->default(0);
            $table->unsignedInteger('rolls')->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('fin_store_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('return_no', 24);
            $table->date('return_date');
            $table->string('kind', 10); // RETURN | TRANSFER
            $table->foreignUlid('lot_id')->constrained('fin_store_lots')->restrictOnDelete();
            $table->foreignUlid('requisition_id')->nullable()->constrained('fin_cut_requisitions')->nullOnDelete();
            $table->decimal('qty_kg', 14, 3);
            $table->unsignedInteger('rolls')->nullable();
            $table->string('to_unit', 120)->nullable();
            $table->string('reason', 120)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'return_no'], 'fin_store_returns_no_unique');
        });
    }

    public function down(): void
    {
        foreach (['fin_store_returns', 'fin_cut_requisition_lines', 'fin_cut_requisitions', 'fin_store_rolls', 'fin_store_moves', 'fin_store_lots', 'fin_store_delivery_lines', 'fin_store_deliveries'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
