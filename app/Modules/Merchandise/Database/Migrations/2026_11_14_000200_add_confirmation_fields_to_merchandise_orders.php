<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Style & Order → Order Confirmation (AnnotechERP 06/07): what confirming an
 * order against a style adds — the buyer's PO, the sub style, the inquiry and
 * sales contract it answers, projection-only and multi flags, pieces per unit,
 * the USD-to-local rate and the TNA template it will follow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merch_orders', function (Blueprint $table): void {
            $table->string('po_no', 64)->nullable();
            $table->string('sub_style', 96)->nullable();
            $table->foreignUlid('inquiry_id')->nullable()->constrained('merch_inquiries')->nullOnDelete();
            $table->string('sales_contract_no', 64)->nullable();
            $table->boolean('projection_only')->default(false);
            $table->boolean('is_multi')->default(false);
            $table->unsignedInteger('pcs_per_unit')->default(1);
            $table->decimal('exchange_rate', 12, 4)->nullable();
            $table->foreignUlid('tna_template_id')->nullable()->constrained('tna_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('merch_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inquiry_id');
            $table->dropConstrainedForeignId('tna_template_id');
            $table->dropColumn(['po_no', 'sub_style', 'sales_contract_no', 'projection_only', 'is_multi', 'pcs_per_unit', 'exchange_rate']);
        });
    }
};
