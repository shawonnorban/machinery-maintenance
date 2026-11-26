<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-Budget → Acc Budget Column Setup (user, 2026-11-19): the BOM worksheet's
 * columns are kept by the company like the requisition sheet's — the same
 * group / column / history tables, told apart by `sheet` (REQ the
 * requisition sheet, BOM the accessories BOM). A BOM line keeps its values by
 * column key (its typed columns move there), so a company's own columns and
 * formulas work on it. Unit Cost Rules: the Cost/Pc formula of each unit, the
 * company's to change.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pb_groups', 'pb_columns', 'pb_column_events'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->string('sheet', 8)->default('REQ')->after('company_id'));
        }
        Schema::table('pb_groups', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'code']);
            $table->unique(['company_id', 'sheet', 'code']);
        });
        Schema::table('pb_columns', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'key']);
            $table->dropUnique(['company_id', 'label']);
            $table->unique(['company_id', 'sheet', 'key']);
            $table->unique(['company_id', 'sheet', 'label']);
        });

        Schema::create('pb_unit_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('uom', 20);
            $table->text('formula');
            $table->string('note', 255)->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'uom']);
        });

        Schema::table('pb_bom_lines', fn (Blueprint $table) => $table->jsonb('values')->nullable()->after('sort_order'));
        $nominated = ['NOMINATED' => 'Nominated', 'NON_NOMINATED' => 'Non Nominated'];
        foreach (DB::table('pb_bom_lines')->get() as $l) {
            $values = array_filter([
                'item' => $l->item_name, 'item_code' => $l->item_code, 'supplier' => $l->supplier, 'nominated' => $nominated[$l->nominated] ?? $l->nominated,
                'item_order_qty' => $l->order_qty === null ? null : (float) $l->order_qty, 'extra_percent' => $l->extra_percent === null ? null : (float) $l->extra_percent,
                'rate' => $l->rate === null ? null : (float) $l->rate, 'unit' => $l->uom, 'body_cons' => $l->body_cons === null ? null : (float) $l->body_cons,
                'item_cons' => $l->item_cons === null ? null : (float) $l->item_cons, 'remarks' => $l->remarks,
            ], fn ($v) => $v !== null && $v !== '');
            DB::table('pb_bom_lines')->where('id', $l->id)->update(['values' => json_encode($values)]);
        }
        Schema::table('pb_bom_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accessory_item_id');
            $table->dropColumn(['item_name', 'item_code', 'supplier', 'nominated', 'order_qty', 'extra_percent', 'rate', 'uom', 'body_cons', 'item_cons', 'remarks']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_unit_rules');
        Schema::table('pb_bom_lines', fn (Blueprint $table) => $table->dropColumn('values'));
        DB::table('pb_columns')->where('sheet', 'BOM')->delete();
        DB::table('pb_groups')->where('sheet', 'BOM')->delete();
        DB::table('pb_column_events')->where('sheet', 'BOM')->delete();
        Schema::table('pb_columns', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'sheet', 'key']);
            $table->dropUnique(['company_id', 'sheet', 'label']);
            $table->unique(['company_id', 'key']);
            $table->unique(['company_id', 'label']);
        });
        Schema::table('pb_groups', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'sheet', 'code']);
            $table->unique(['company_id', 'code']);
        });
        foreach (['pb_groups', 'pb_columns', 'pb_column_events'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn('sheet'));
        }
    }
};
