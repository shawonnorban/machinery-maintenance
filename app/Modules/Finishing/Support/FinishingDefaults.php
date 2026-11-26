<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Support;

use App\Modules\Finishing\Models\FinProcessGroup;
use App\Modules\Finishing\Models\FinProcessType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Finishing Line lists a company starts with, as Brickland ships them
 * (AnnotechERP 18/01): six process groups, Dyeing Finishing's three
 * sub-groups and its eight processes. Made the first time a list is opened,
 * and only into an empty list; insertOrIgnore because a page opens several
 * requests at once.
 */
final class FinishingDefaults
{
    /** code => name */
    public const GROUPS = ['KNITTING' => 'Knitting', 'YARN_DYEING' => 'Yarn Dyeing', 'DYEING' => 'Dyeing', 'DYEING_FINISHING' => 'Dyeing Finishing', 'AOP' => 'AOP', 'YARN_TWIST' => 'Yarn Twist'];

    /** code => name, under Dyeing Finishing */
    public const SUBGROUPS = ['DF-WET' => 'Wet Finishing', 'DF-DRY' => 'Dry Finishing', 'DF-SUR' => 'Surface Finishing'];

    /** [code, name, short, sub-group, link (code), wip stage, rate, loss %] */
    public const PROCESSES = [
        ['DF-HS', 'Heat-Set', 'HS', 'DF-DRY', null, 'HEAT_SET', 6.50, 1.5],
        ['DF-DW', 'Dewatering', 'DW', 'DF-WET', 'DF-HS', 'DEWATERING', 3.25, 0.5],
        ['DF-DRY', 'Drying', 'DRY', 'DF-DRY', 'DF-DW', 'DRYING', 5.00, 2],
        ['DF-ST', 'Stentering', 'ST', 'DF-DRY', 'DF-DRY', 'STENTERING', 8.00, 1],
        ['DF-CMP', 'Open Compacting', 'OC', 'DF-DRY', 'DF-ST', 'COMPACTING', 7.50, 3],
        ['DF-CAL', 'Calendering', 'CAL', 'DF-SUR', 'DF-DRY', 'CALENDERING', 6.00, 1.5],
        ['DF-BR', 'Brush Only', 'BR', 'DF-SUR', 'DF-ST', 'BRUSHING', 9.00, 2.5],
        ['DF-PCH', 'Peach Only', 'PCH', 'DF-SUR', 'DF-BR', 'BRUSHING', 11.00, 3.5],
    ];

    public static function ensure(string $companyId): void
    {
        if (FinProcessGroup::query()->exists()) {
            return;
        }
        $now = now();
        $order = 0;
        DB::table('fin_process_groups')->insertOrIgnore(array_map(function (string $code) use ($companyId, &$order, $now) {
            return ['id' => (string) Str::ulid(), 'company_id' => $companyId, 'code' => $code, 'name' => self::GROUPS[$code], 'sort_order' => ++$order, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        }, array_keys(self::GROUPS)));
        $df = DB::table('fin_process_groups')->where('company_id', $companyId)->where('code', 'DYEING_FINISHING')->value('id');
        $order = 0;
        DB::table('fin_process_subgroups')->insertOrIgnore(array_map(function (string $code) use ($companyId, $df, &$order, $now) {
            return ['id' => (string) Str::ulid(), 'company_id' => $companyId, 'group_id' => $df, 'code' => $code, 'name' => self::SUBGROUPS[$code], 'sort_order' => ++$order, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        }, array_keys(self::SUBGROUPS)));
        if (FinProcessType::query()->exists()) {
            return;
        }
        $subs = DB::table('fin_process_subgroups')->where('company_id', $companyId)->pluck('id', 'code');
        $rows = [];
        foreach (self::PROCESSES as $i => [$code, $name, $short, $sub, , $stage, $rate, $loss]) {
            $rows[$code] = ['id' => (string) Str::ulid(), 'company_id' => $companyId, 'code' => $code, 'name' => $name, 'short_name' => $short, 'group_id' => $df, 'subgroup_id' => $subs[$sub] ?? null, 'link_process_id' => null, 'wip_stage' => $stage, 'rate' => $rate, 'loss_pct' => $loss, 'display_order' => $i + 1, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('fin_process_types')->insertOrIgnore(array_values($rows));
        $ids = DB::table('fin_process_types')->where('company_id', $companyId)->pluck('id', 'code');
        foreach (self::PROCESSES as [$code, , , , $link]) {
            if ($link) {
                DB::table('fin_process_types')->where('company_id', $companyId)->where('code', $code)->whereNull('link_process_id')->update(['link_process_id' => $ids[$link] ?? null]);
            }
        }
    }
}
