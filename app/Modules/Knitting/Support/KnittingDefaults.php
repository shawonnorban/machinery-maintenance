<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Support;

use App\Modules\Knitting\Models\KnitRefEntry;
use App\Modules\Knitting\Models\KnitYarnCount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Knit Production lists a company starts with, as Brickland ships them
 * (AnnotechERP 15/01): six entries in each machine reference list and twenty
 * five yarn counts. Made the first time a list is opened, and only into an
 * empty list.
 */
final class KnittingDefaults
{
    /** list => [code => name] */
    public const REF_LISTS = [
        'MC_TYPE' => ['SJ' => 'Single Jersey', 'RIB' => 'Rib', 'INTL' => 'Interlock', 'FLEECE' => 'Fleece / Terry', 'JCQ' => 'Jacquard', 'FLAT' => 'Flat Bed (Collar/Cuff)'],
        'BRAND' => ['MAYER' => 'Mayer & Cie', 'TERROT' => 'Terrot', 'FUKUHARA' => 'Fukuhara', 'PAILUNG' => 'Pailung', 'ORIZIO' => 'Orizio', 'WELLKNIT' => 'Wellknit'],
        'DIA' => ['D26' => '26"', 'D30' => '30"', 'D32' => '32"', 'D34' => '34"', 'D36' => '36"', 'D38' => '38"'],
        'GG' => ['GG14' => '14GG', 'GG18' => '18GG', 'GG20' => '20GG', 'GG24' => '24GG', 'GG28' => '28GG', 'GG32' => '32GG'],
    ];

    /** [count_no, system, description, value, single] in Brickland's order */
    public const YARN_COUNTS = [
        ['50D', 'D', '50 D', 50, true], ['YC-20-1', 'Ne', '20/1', 20, true], ['75D', 'D', '75 D', 75, true], ['YC-24-1', 'Ne', '24/1', 24, true],
        ['100D', 'D', '100 D', 100, true], ['YC-26-1', 'Ne', '26/1', 26, true], ['8/s', 'Ne', '8/s', 8, true], ['YC-30-1', 'Ne', '30/1', 30, true],
        ['10/s', 'Ne', '10/s', 10, false], ['YC-32-1', 'Ne', '32/1', 32, true], ['12/s', 'Ne', '12/s', 12, true], ['YC-40-1', 'Ne', '40/1', 40, true],
        ['14/s', 'Ne', '14/s', 14, true], ['16/s', 'Ne', '16/s', 16, true], ['18/s', 'Ne', '18/s', 18, true], ['20/s', 'Ne', '20/s', 20, true],
        ['22/s', 'Ne', '22/s', 22, true], ['24/s', 'Ne', '24/s', 24, true], ['26/s', 'Ne', '26/s', 26, true], ['28/s', 'Ne', '28/s', 28, true],
        ['30/s', 'Ne', '30/s', 30, true], ['34/s', 'Ne', '34/s', 34, true], ['40/s', 'Ne', '40/s', 40, true], ['20/2', 'Ne', '20/2', 20, false],
        ['30/2', 'Ne', '30/2', 30, false],
    ];

    public static function refLists(string $companyId): void
    {
        foreach (self::REF_LISTS as $list => $entries) {
            if (KnitRefEntry::query()->where('list', $list)->exists()) {
                continue;
            }
            $order = 0;
            $now = now();
            // insertOrIgnore: a page opens several requests at once, and each may arrive here first.
            DB::table('knit_ref_entries')->insertOrIgnore(array_map(function (string $code) use ($entries, $list, $companyId, &$order, $now) {
                return ['id' => (string) Str::ulid(), 'company_id' => $companyId, 'list' => $list, 'code' => $code, 'name' => $entries[$code], 'sort_order' => ++$order, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }, array_keys($entries)));
        }
    }

    public static function yarnCounts(string $companyId): void
    {
        if (KnitYarnCount::query()->exists()) {
            return;
        }
        $now = now();
        DB::table('knit_yarn_counts')->insertOrIgnore(array_map(fn (array $c, int $i) => [
            'id' => (string) Str::ulid(), 'company_id' => $companyId, 'count_no' => $c[0], 'count_system' => $c[1], 'description' => $c[2], 'count_value' => $c[3],
            'is_single' => $c[4], 'display_order' => $i + 1, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ], self::YARN_COUNTS, array_keys(self::YARN_COUNTS)));
    }
}
