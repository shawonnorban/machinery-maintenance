<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the fabric
 * supplier return challan — fabric that goes back to a supplier from a
 * finalised fabric MRR (rejected, excess, off shade …), carried out of a
 * store; finalised, it is locked and the batches leave the purchased-fabric
 * stock ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_pfab_returns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('challan_no', 32);
            $table->string('return_type', 16);
            $table->ulid('unit_company_id');
            $table->ulid('supplier_id');
            $table->ulid('mrr_id')->nullable();
            $table->date('challan_date');
            $table->ulid('store_id');
            $table->ulid('return_by');
            $table->string('gate_pass_no', 40)->nullable();
            $table->string('vehicle_no', 40)->nullable();
            $table->string('driver_name', 80)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->timestamp('finalized_at')->nullable();
            $table->ulid('finalized_by')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'challan_no']);
            $table->index(['company_id', 'mrr_id']);
        });

        Schema::create('merch_pfab_return_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('return_id')->constrained('merch_pfab_returns')->cascadeOnDelete();
            $table->ulid('mrr_line_id')->nullable();
            $table->string('description', 200);
            $table->string('uom', 24)->nullable();
            $table->decimal('qty', 14, 4);
            $table->string('reason', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'mrr_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_pfab_return_lines');
        Schema::dropIfExists('merch_pfab_returns');
    }
};
