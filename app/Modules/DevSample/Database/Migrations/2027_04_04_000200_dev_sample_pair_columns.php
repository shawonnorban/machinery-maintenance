<?php

declare(strict_types=1);

use App\Modules\DevSample\Support\DsLegacy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dev Sample → "pairs" columns (user 2026-11-21: several colours with their
 * pieces on one sample, and Norban's price levels, as before): a column of
 * lines, each a name and a number — Color with Qty (colour: pcs, its number
 * the total) and Buyer Target / FOB Price (label: $, its number the first
 * line, the summary price). `pair_reduce` says which number a formula and
 * the summary cards read: SUM of the lines or the FIRST line. Price Levels
 * goes: its levels are the price columns' own lines now.
 */
return new class extends Migration
{
    private const PAIRS = [
        'colours' => ['reduce' => 'SUM', 'decimals' => 0, 'prefix' => null, 'suffix' => 'Pcs'],
        'target_price' => ['reduce' => 'FIRST', 'decimals' => 2, 'prefix' => '$', 'suffix' => null],
        'fob_price' => ['reduce' => 'FIRST', 'decimals' => 2, 'prefix' => '$', 'suffix' => null],
    ];

    public function up(): void
    {
        Schema::table('ds_columns', function (Blueprint $table): void {
            $table->string('pair_reduce', 8)->nullable()->after('kpi'); // SUM | FIRST (pairs columns)
        });

        foreach (self::PAIRS as $key => $p) {
            DB::table('ds_columns')->where('key', $key)->update([
                'type' => 'pairs', 'source' => null, 'multiple' => false, 'pair_reduce' => $p['reduce'], 'decimals' => $p['decimals'],
                'prefix' => $p['prefix'], 'suffix' => $p['suffix'], 'kpi' => null, 'color_rules' => null, 'updated_at' => now(),
            ]);
        }
        // A formula never read Price Levels (it was text); its column goes once its levels are in the prices.
        DB::table('ds_columns')->where('key', 'price_levels')->delete();

        foreach (DB::table('ds_samples')->orderBy('id')->get(['id', 'values']) as $row) {
            $v = json_decode((string) $row->values, true) ?: [];
            DB::table('ds_samples')->where('id', $row->id)->update(['values' => json_encode(DsLegacy::toPairs($v), JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        Schema::table('ds_columns', function (Blueprint $table): void {
            $table->dropColumn('pair_reduce');
        });
    }
};
