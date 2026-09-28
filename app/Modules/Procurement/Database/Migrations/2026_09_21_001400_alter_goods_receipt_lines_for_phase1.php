<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The goods receipt learns to post stock (Procurement spec §9, Phase 1).
 *
 * A completed line needs a bin to land in and the item's code and name as
 * they were on the day. The purchase order tables now exist, so the bare
 * `po_id` / `po_line_id` become real foreign keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreign('po_id', 'goods_receipts_po_fk')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->foreignUlid('bin_id')->nullable()->after('item_id')->constrained('bins')->nullOnDelete();
            $table->string('item_code', 64)->nullable()->after('bin_id');
            $table->string('item_name')->nullable()->after('item_code');
            // What passed the gate versus what was accepted onto the shelf;
            // null until inspection (Phase 2) says otherwise, meaning "all".
            $table->decimal('accepted_qty', 18, 4)->nullable()->after('received_qty');
            $table->text('notes')->nullable()->after('inspection_status');

            $table->foreign('po_line_id', 'goods_receipt_lines_po_line_fk')->references('id')->on('purchase_order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table): void {
            $table->dropForeign('goods_receipt_lines_po_line_fk');
            $table->dropConstrainedForeignId('bin_id');
            $table->dropColumn(['item_code', 'item_name', 'accepted_qty', 'notes']);
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropForeign('goods_receipts_po_fk');
            $table->dropColumn('cancelled_at');
        });
    }
};
