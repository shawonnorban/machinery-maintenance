<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Order Summary (AnnotechERP 32), more of the factory's Excel look (user,
 * 2026-11-19): the Delay group's headers cyan and the sample / planning
 * groups' pink, fixed text colours (TP Rcvd and Last Update orange, Reprint
 * and Shipout dates green), and Excel's SUBTOTAL columns in the totals row.
 * The merchandiser columns pick from Order Summary's own lookup list.
 * Only settings nobody has changed are touched.
 */
return new class extends Migration
{
    private const TEXT = ['tp_rcvd_date' => 'orange', 'last_update' => 'orange', 'reprint_date' => 'green', 'shipout_date' => 'green'];

    private const TOTALS = ['yarn_value_as_per_requirement', 'yarn_purchase_balance_value', 'cutting_req_quantity_pcs', 'yarn_purchase_balance_kg', 'short_excess_pcs', 'shipout_value_usd'];

    private const MERCHANDISERS = ['marketing_merchandiser', 'production_merchandiser', 'buyer_merchandiser'];

    public function up(): void
    {
        Schema::table('os_columns', function (Blueprint $table): void {
            $table->string('text_tone', 16)->nullable()->after('color_rules');
        });
        foreach (self::TEXT as $key => $tone) {
            DB::table('os_columns')->where('key', $key)->update(['text_tone' => $tone]);
        }
        // Last Update was an orange cell; Excel shows orange text on white.
        DB::table('os_columns')->where('key', 'last_update')->whereRaw('color_rules::text = ?', ['[{"op":"any","tone":"orange"}]'])->update(['color_rules' => null]);
        DB::table('os_columns')->whereIn('key', self::TOTALS)->whereNull('kpi')->update(['kpi' => 'SUM']);

        DB::table('os_groups')->where('code', '04')->where('is_system', true)->where(fn ($q) => $q->whereNull('tone')->orWhere('tone', 'red'))->update(['tone' => 'cyan']);
        DB::table('os_groups')->whereIn('code', ['13', '14', '15', '16'])->where('is_system', true)->whereNull('tone')->update(['tone' => 'pink']);

        if (! DB::table('lookup_tables')->whereNull('company_id')->where('code', 'OS_MERCHANDISER')->exists()) {
            DB::table('lookup_tables')->insert([
                'id' => Str::lower((string) Str::ulid()), 'company_id' => null, 'code' => 'OS_MERCHANDISER', 'name' => 'Order Summary – Merchandiser',
                'description' => 'Order Summary dropdown list (Norban Base Module).', 'is_system' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('os_columns')->whereIn('key', self::MERCHANDISERS)->where('source', 'merchandisers')->update(['source' => 'lookup:OS_MERCHANDISER']);
    }

    public function down(): void
    {
        DB::table('os_columns')->whereIn('key', self::MERCHANDISERS)->where('source', 'lookup:OS_MERCHANDISER')->update(['source' => 'merchandisers']);
        Schema::table('os_columns', function (Blueprint $table): void {
            $table->dropColumn('text_tone');
        });
    }
};
