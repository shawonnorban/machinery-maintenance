<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the procurement
 * posting setup — per company, the voucher type and the ledger heads a
 * finalised accessories receipt's draft voucher is raised on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_proc_posting_setups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('unit_company_id');
            $table->string('voucher_type', 8);
            $table->ulid('inventory_ledger_id');
            $table->ulid('vat_ledger_id');
            $table->ulid('payable_ledger_id');
            $table->ulid('tds_ledger_id');
            $table->ulid('vds_ledger_id');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'unit_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_proc_posting_setups');
    }
};
