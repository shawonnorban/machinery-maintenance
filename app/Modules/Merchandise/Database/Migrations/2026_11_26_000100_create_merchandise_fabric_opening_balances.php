<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise Desk → Trims & Accessories (AnnotechERP 06/06): the purchased
 * fabric opening balance — opening stock carried into the period, one fabric
 * batch per row, taken Draft → Submitted → Approved → Posted → Locked with
 * every step kept; and the purchased-fabric stock ledger a posted balance puts
 * its batches on, at their opening cost in BDT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merch_pfab_obs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('ob_no', 32);
            $table->ulid('unit_company_id');
            $table->ulid('store_id');
            $table->date('ob_date');
            $table->ulid('order_id')->nullable();
            $table->string('currency', 3);
            $table->decimal('conv_rate', 12, 6)->default(1);
            $table->decimal('usd_bdt', 12, 4)->nullable();
            $table->string('source', 24);
            $table->string('reference_no', 80)->nullable();
            $table->string('attachment', 255)->nullable();
            $table->ulid('attachment_file_id')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 12)->default('DRAFT');
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'ob_no']);
        });

        Schema::create('merch_pfab_ob_lines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('ob_id')->constrained('merch_pfab_obs')->cascadeOnDelete();
            $table->string('category', 24);
            $table->string('description', 200)->nullable();
            $table->string('fabric_item', 120);
            $table->ulid('colour_id')->nullable();
            $table->ulid('fabric_type_id')->nullable();
            $table->string('fabric_group', 12);
            $table->ulid('composition_id')->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->decimal('dia', 8, 2)->nullable();
            $table->string('dia_uom', 8)->nullable();
            $table->string('dia_type', 8)->nullable();
            $table->string('batch_no', 60)->nullable();
            $table->unsignedInteger('rolls')->nullable();
            $table->string('roll_nos', 500)->nullable();
            $table->string('uom', 8);
            $table->decimal('qty', 14, 4);
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('cost_bdt', 16, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'batch_no']);
        });

        Schema::create('merch_pfab_ob_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('ob_id')->constrained('merch_pfab_obs')->cascadeOnDelete();
            $table->string('action', 12);
            $table->string('from_status', 12)->nullable();
            $table->string('to_status', 12);
            $table->ulid('user_id')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
        });

        Schema::create('merch_pfab_ledger', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->ulid('store_id');
            $table->string('source', 12);
            $table->ulid('source_id');
            $table->ulid('source_line_id');
            $table->date('txn_date');
            $table->string('batch_no', 60)->nullable();
            $table->string('fabric_item', 120);
            $table->string('description', 200)->nullable();
            $table->ulid('colour_id')->nullable();
            $table->ulid('fabric_type_id')->nullable();
            $table->ulid('composition_id')->nullable();
            $table->string('uom', 8);
            $table->decimal('qty', 14, 4);
            $table->decimal('cost_bdt', 16, 2);
            $table->timestamps();
            $table->index(['company_id', 'source', 'source_id']);
            $table->index(['company_id', 'store_id', 'batch_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_pfab_ledger');
        Schema::dropIfExists('merch_pfab_ob_events');
        Schema::dropIfExists('merch_pfab_ob_lines');
        Schema::dropIfExists('merch_pfab_obs');
    }
};
