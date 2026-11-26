<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dye House → S/C In-House (AnnotechERP 17/10): dyeing and finishing taken in
 * from another party.
 *
 * - `dye_sc_quotations.side`: OUT (17/01, rates a party charges us) or IN
 *   (In-House Sub-Contract Party Rate, rates we charge a party).
 * - `dye_sci_programmes`: Sub-Contract Order Register (DFSCI-YYYYMM####) — a
 *   party's programme: its buyer and order, and the processes taken on.
 * - `dye_sci_receipts`: In-House Sub-Contract Fabric Receipt — the party's
 *   fabric received in against a programme, on their own challan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dye_sc_quotations', function (Blueprint $table): void {
            $table->string('side', 3)->default('OUT')->after('rate_group');
        });
        Schema::create('dye_sci_programmes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('program_no', 24);
            $table->date('prog_date');
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('merch_orders')->nullOnDelete();
            $table->boolean('dyeing')->default(false);
            $table->boolean('dyeing_finishing')->default(false);
            // DRAFT, SUBMITTED, PARAMS_UPDATED, DESPATCHED, COMPLETED, CANCELLED.
            $table->string('status', 16);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'program_no']);
            $table->index(['company_id', 'prog_date']);
        });
        Schema::create('dye_sci_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 40);
            $table->date('challan_date');
            $table->foreignUlid('programme_id')->constrained('dye_sci_programmes')->restrictOnDelete();
            $table->foreignUlid('supplier_id')->constrained('mp_suppliers')->restrictOnDelete();
            $table->foreignUlid('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->decimal('qty_kg', 14, 3);
            $table->text('remarks')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // The party's own challan number, once per party.
            $table->unique(['company_id', 'supplier_id', 'challan_no']);
            $table->index(['company_id', 'challan_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dye_sci_receipts');
        Schema::dropIfExists('dye_sci_programmes');
        Schema::table('dye_sc_quotations', fn (Blueprint $table) => $table->dropColumn('side'));
    }
};
