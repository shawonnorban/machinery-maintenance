<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Machinery's starter spare part categories. Categories are one list per
 * company — `item_categories`, kept on General Store → Categories (the user's
 * decision of 2026-11-15) — so the starter set is put into a company's list
 * the first time its spare parts need categories, never as platform rows.
 */
final class SparePartCategories
{
    /** code => [name, name (Bangla)] */
    public const STARTER = [
        'SEWING_PARTS' => ['Sewing machine parts', 'সেলাই মেশিনের পার্টস'],
        'CUTTING_PARTS' => ['Cutting machine parts', 'কাটিং মেশিনের পার্টস'],
        // Needles and sinkers are bought by the thousand and consumed by the
        // hundred; a knitting floor that cannot see its needle spend
        // separately cannot see its largest recurring cost at all.
        'KNITTING_PARTS' => ['Knitting parts (needles, sinkers, cams)', 'নিটিং পার্টস (সুই, সিংকার, ক্যাম)'],
        'DYEING_PARTS' => ['Dyeing parts (pumps, seals, nozzles)', 'ডাইং পার্টস (পাম্প, সিল, নোজল)'],
        'FINISHING_PARTS' => ['Finishing parts (chains, clips, blankets)', 'ফিনিশিং পার্টস (চেইন, ক্লিপ, ব্ল্যাঙ্কেট)'],
        'ELECTRICAL' => ['Electrical', 'বৈদ্যুতিক'],
        'MECHANICAL' => ['Mechanical', 'যান্ত্রিক'],
        'INSTRUMENTATION' => ['Instrumentation and sensors', 'ইনস্ট্রুমেন্টেশন ও সেন্সর'],
        'BEARINGS' => ['Bearings and bushes', 'বেয়ারিং ও বুশ'],
        'BELTS_CHAINS' => ['Belts and chains', 'বেল্ট ও চেইন'],
        'SEALS_GASKETS' => ['Seals and gaskets', 'সিল ও গ্যাসকেট'],
        'VALVES_FITTINGS' => ['Valves and fittings', 'ভালভ ও ফিটিংস'],
        'FILTERS' => ['Filters and strainers', 'ফিল্টার ও স্ট্রেইনার'],
        'PNEUMATIC' => ['Pneumatic', 'নিউম্যাটিক'],
        'HYDRAULIC' => ['Hydraulic', 'হাইড্রলিক'],
        'LUBRICANTS' => ['Lubricants and oils', 'তেল ও লুব্রিক্যান্ট'],
        'FASTENERS' => ['Fasteners', 'নাট-বোল্ট'],
        'STEAM_UTILITY' => ['Steam and utility', 'স্টিম ও ইউটিলিটি'],
        // Boiler and water treatment chemicals only. Dyes and process
        // chemicals are production stock and are not maintained here.
        'UTILITY_CHEMICALS' => ['Boiler and water treatment chemicals', 'বয়লার ও পানি ট্রিটমেন্ট কেমিক্যাল'],
        'SAFETY' => ['Safety equipment', 'নিরাপত্তা সরঞ্জাম'],
        'CONSUMABLES' => ['Consumables', 'ভোগ্য সামগ্রী'],
    ];

    /** Adds the starter categories a company does not have yet (by code). */
    public static function ensure(string $companyId): void
    {
        $have = DB::table('item_categories')->where('company_id', $companyId)->whereIn('code', array_keys(self::STARTER))->pluck('code')->all();
        $rows = [];
        foreach (self::STARTER as $code => [$name, $nameBn]) {
            if (! in_array($code, $have, true)) {
                $rows[] = [
                    'id' => (string) Str::ulid(), 'company_id' => $companyId, 'parent_id' => null, 'code' => $code, 'name' => $name,
                    'name_bn' => $nameBn, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }
        if ($rows !== []) {
            DB::table('item_categories')->insert($rows);
        }
    }
}
