<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store & Material (AnnotechERP 09): fabric received roll by roll.
 *
 * - `sm_rolls`: one roll on a GRN's fabric line — its number, dye lot, GSM,
 *   width and quantity; the line's rolls add up to the line. A roll number
 *   is the company's own and is not reused while its GRN stands.
 * - `sm_roll_moves`: every movement of a roll — received, out on a Material
 *   Out, back on a Material Return, or reversed with its GRN — the roll's
 *   available quantity read from it, as a bucket's is from `sm_ledger`.
 * - `sm_material_returns.roll_id`: the roll a return comes back on, when
 *   what went out was taken by roll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_rolls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('grn_id')->constrained('sm_grns')->cascadeOnDelete();
            $table->foreignUlid('grn_line_id')->constrained('sm_grn_lines')->cascadeOnDelete();
            $table->foreignUlid('stock_id')->constrained('sm_stocks')->restrictOnDelete();
            $table->foreignUlid('item_id')->constrained('mp_items')->restrictOnDelete();
            $table->string('roll_no', 40);
            $table->string('dye_lot', 60)->nullable();
            $table->string('gsm', 32)->nullable();
            $table->string('width', 32)->nullable();
            $table->string('uom', 16);
            $table->decimal('qty', 14, 3);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
            $table->index(['company_id', 'roll_no']);
            $table->index(['company_id', 'stock_id']);
        });

        Schema::create('sm_roll_moves', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUlid('roll_id')->constrained('sm_rolls')->cascadeOnDelete();
            $table->date('entry_date');
            // GRN, MATERIAL_OUT, MATERIAL_RETURN or REVERSAL.
            $table->string('type', 16);
            $table->string('reference', 24);
            $table->ulid('source_id');
            $table->decimal('in_qty', 14, 3)->default(0);
            $table->decimal('out_qty', 14, 3)->default(0);
            $table->decimal('return_qty', 14, 3)->default(0);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();
            $table->index(['company_id', 'roll_id']);
            $table->index(['company_id', 'source_id']);
        });

        Schema::table('sm_material_returns', function (Blueprint $table): void {
            $table->foreignUlid('roll_id')->nullable()->after('material_out_id')->constrained('sm_rolls')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sm_material_returns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('roll_id');
        });
        Schema::dropIfExists('sm_roll_moves');
        Schema::dropIfExists('sm_rolls');
    }
};
