<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Order Summary (user, 2026-11-22):
 *  - Colors works as Dev Sample's Color with Qty — a pairs column, lines of
 *    a colour and its quantity (Navy: 600 Pcs), the lines' total being the
 *    number a formula reads. The colours already entered become lines with
 *    no quantity yet.
 *  - Buyer Merchandiser is typed in (a text column), no longer picked from
 *    the merchandiser list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('os_columns', 'pair_reduce')) {
            Schema::table('os_columns', function (Blueprint $table): void {
                $table->string('pair_reduce', 8)->nullable()->after('kpi'); // SUM | FIRST (pairs columns)
            });
        }
        DB::table('os_columns')->where('key', 'colors')->update([
            'type' => 'pairs', 'multiple' => false, 'pair_reduce' => 'SUM', 'decimals' => 0, 'suffix' => 'Pcs', 'updated_at' => now(),
        ]);
        DB::table('os_columns')->where('key', 'colors')->where('width', '<', 170)->update(['width' => 170]);
        DB::statement(<<<'SQL'
            update os_orders set "values" = jsonb_set("values", '{colors}', (
                select coalesce(jsonb_agg(jsonb_build_object('label', e, 'value', null)), '[]'::jsonb)
                from jsonb_array_elements_text("values"->'colors') e where btrim(e) <> ''
            ))
            where jsonb_typeof("values"->'colors') = 'array' and jsonb_typeof("values"->'colors'->0) = 'string'
            SQL);
        DB::table('os_columns')->where('key', 'buyer_merchandiser')->update(['type' => 'text', 'source' => null, 'multiple' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            update os_orders set "values" = jsonb_set("values", '{colors}', (
                select coalesce(jsonb_agg(l->>'label'), '[]'::jsonb) from jsonb_array_elements("values"->'colors') l
            ))
            where jsonb_typeof("values"->'colors') = 'array' and jsonb_typeof("values"->'colors'->0) = 'object'
            SQL);
        DB::table('os_columns')->where('key', 'colors')->update(['type' => 'multi', 'multiple' => true, 'pair_reduce' => null, 'decimals' => null, 'suffix' => null]);
        DB::table('os_columns')->where('key', 'buyer_merchandiser')->update(['type' => 'dropdown', 'source' => 'lookup:OS_MERCHANDISER']);
        Schema::table('os_columns', fn (Blueprint $table) => $table->dropColumn('pair_reduce'));
    }
};
