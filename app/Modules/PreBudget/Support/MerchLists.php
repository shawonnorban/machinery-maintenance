<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One list per field for Order Summary and Pre-Budget (user, 2026-11-23):
 * Order Type, Item, Wash, Fabric Type, Fabric Composition and Sustainability
 * are the requisition sheet's lists (pb_list_values), and Order Summary's
 * columns pick from them too. A value is matched to its list ignoring case
 * and spaces ("WOVEN" → "Woven", "Bio+Recycle" → "Bio + Recycle"), then by
 * the spellings the user approved for the old Order Summary values; one the
 * list still lacks can be added to it, so both sheets keep the same words.
 */
final class MerchLists
{
    /** Order Summary column → the requisition list it picks from. */
    public const OS_COLUMNS = [
        'order_type' => 'ORDER_TYPE',
        'item' => 'ITEM',
        'wash_info_at_garmenting' => 'WASH_TYPE',
        'fabric_type' => 'FABRIC_TYPE',
        'composition' => 'FABRIC_COMPOSITION',
        'sustainability' => 'SUSTAINABILITY',
    ];

    /** Old Order Summary spellings → the list's value (the user's mapping of 2026-11-24), keyed by squash(). */
    private const ALIASES = [
        'ITEM' => ['pant' => 'Long pant', 'sweatzipper' => 'Sweat full Zipper'],
        'WASH_TYPE' => ['nowash' => 'No', 'garmentwash' => 'Garments Wash'],
        'FABRIC_TYPE' => ['n/a' => 'Other'],
        'SUSTAINABILITY' => [
            'physicalbci+recycle' => 'Physical BCI + Recycle',
            'physicalbci+recyclecotton' => 'Physical BCI + Recycle',
            'physbci+rec.cot' => 'Physical BCI + Recycle',
            'phy.bci+rec.cot+rec.pe' => 'Physical BCI + Recycle Cotton + Recycle Polyester',
        ],
    ];

    /** Lower case, no spaces: how two spellings of one value are told apart. */
    public static function squash(string $v): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', '', $v));
    }

    /**
     * The list's own spelling of a value among its names, or null when the list holds neither it nor an
     * approved alias of it.
     *
     * @param  iterable<string>  $names  the list's values
     */
    public static function find(string $list, string $value, iterable $names): ?string
    {
        $key = self::squash($value);
        if ($key === '') {
            return null;
        }
        $alias = self::ALIASES[$list][$key] ?? null;
        $aliasKey = $alias !== null ? self::squash($alias) : null;
        $byAlias = null;
        foreach ($names as $n) {
            $k = self::squash((string) $n);
            if ($k === $key) {
                return (string) $n;
            }
            if ($k === $aliasKey) {
                $byAlias = (string) $n;
            }
        }

        return $byAlias;
    }

    /** The list's spelling of a value; added to the list first when it holds no such value (an alias's target included). */
    public static function ensure(string $companyId, string $list, string $value, ?string $userId = null): string
    {
        $names = self::names($companyId, $list);
        $hit = self::find($list, $value, $names);
        if ($hit !== null) {
            return $hit;
        }
        $name = mb_substr(self::ALIASES[$list][self::squash($value)] ?? trim($value), 0, 255);
        DB::table('pb_list_values')->insert([
            'id' => (string) Str::ulid(), 'company_id' => $companyId, 'list' => $list, 'name' => $name,
            'sort_order' => 500 + count($names), 'active' => true, 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $name;
    }

    /** @return list<string> the active values of one list, in its order */
    public static function names(string $companyId, string $list): array
    {
        return DB::table('pb_list_values')->where('company_id', $companyId)->where('list', $list)->where('active', true)
            ->orderBy('sort_order')->orderBy('name')->pluck('name')->map(fn ($n) => (string) $n)->all();
    }
}
