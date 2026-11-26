<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk's receipts take the draft a Material Procurement follow-up
 * raises (AnnotechERP 08/06, Raise GRN): a purchased-fabric receipt line no
 * longer needs an order line, and either receipt's line remembers the
 * requisition line it was received against.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_pfab_mrr_lines', function (Blueprint $table): void {
            $table->ulid('po_line_id')->nullable()->change();
            $table->ulid('mp_requisition_line_id')->nullable();
        });
        Schema::table('merch_acc_mrr_lines', function (Blueprint $table): void {
            $table->ulid('mp_requisition_line_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('merch_acc_mrr_lines', function (Blueprint $table): void {
            $table->dropColumn('mp_requisition_line_id');
        });
        Schema::table('merch_pfab_mrr_lines', function (Blueprint $table): void {
            $table->dropColumn('mp_requisition_line_id');
        });
    }
};
