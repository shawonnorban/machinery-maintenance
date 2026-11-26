<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Support;

use App\Modules\WorkStudy\Models\WsGarmentItem;
use App\Modules\WorkStudy\Models\WsItemGroup;
use Illuminate\Support\Str;

/**
 * What Work Study → Item Specification (AnnotechERP 14/04) shares: the item
 * groups and category codes as Brickland ships them (Knit Tops GRP-KNIT,
 * Basic T-Shirt ITM-TSHIRT …), made the first time the register is opened —
 * a group every existing category names comes along, and every category
 * gets a code — and the nature a sewing SMV falls into.
 */
final class WsSpecs
{
    /** group => [code, sort] */
    public const GROUPS = ['Knit Tops' => ['GRP-KNIT', 10], 'Bottoms' => ['GRP-BOTM', 20], 'Outerwear' => ['GRP-OUTR', 30]];

    /** category => code */
    public const CATEGORY_CODES = ['Basic T-Shirt' => 'ITM-TSHIRT', 'Polo Shirt' => 'ITM-POLO', 'Pullover Hoodie' => 'ITM-HOODIE', 'Twill Trouser' => 'ITM-TROUSER'];

    /** The printed order-quantity bands a tech variation's SMVs are kept for. */
    public const BANDS = ['band1' => '1 - 2000', 'band2' => '2001 - 5000', 'band3' => '5001 - 7000', 'band4' => '7001 - 10000', 'band5' => '10001 - 15000', 'band6' => '15001 +'];

    /** nature => the highest sewing SMV it holds (Basic 0–12 …). */
    public const NATURE_BANDS = ['BASIC' => 12, 'SEMI_CRITICAL' => 20, 'CRITICAL' => 35, 'HIGHLY_CRITICAL' => 99];

    public static function ensure(string $companyId): void
    {
        WorkStudyDefaults::garmentItems($companyId);
        if (! WsItemGroup::query()->exists()) {
            $sort = 0;
            foreach (self::GROUPS as $name => [$code, $order]) {
                WsItemGroup::query()->create(['company_id' => $companyId, 'name' => $name, 'code' => $code, 'sort_order' => $sort = $order]);
            }
            foreach (WsGarmentItem::query()->pluck('item_group')->unique() as $name) {
                if (! WsItemGroup::query()->where('name', $name)->exists()) {
                    WsItemGroup::query()->create(['company_id' => $companyId, 'name' => $name, 'code' => self::uniqueCode('GRP', $name, fn ($c) => WsItemGroup::query()->where('code', $c)->exists()), 'sort_order' => $sort += 10]);
                }
            }
        }
        foreach (WsGarmentItem::query()->whereNull('code')->orderBy('created_at')->get()->groupBy('item_group') as $items) {
            $sl = (int) WsGarmentItem::query()->where('item_group', $items->first()->item_group)->max('sl');
            foreach ($items as $item) {
                $code = self::CATEGORY_CODES[$item->name] ?? null;
                if (! $code || WsGarmentItem::query()->where('code', $code)->exists()) {
                    $code = self::uniqueCode('ITM', $item->name, fn ($c) => WsGarmentItem::query()->where('code', $c)->exists());
                }
                $item->update(['code' => $code, 'sl' => $item->sl ?: ++$sl]);
            }
        }
    }

    /** A free code from a name: GRP-KIDS, ITM-CREWNECK, then -2, -3 … */
    public static function uniqueCode(string $prefix, string $name, callable $taken): string
    {
        $base = $prefix.'-'.Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name)) ?: 'X', 10, ''));
        $code = $base;
        for ($n = 2; $taken($code); $n++) {
            $code = "{$base}-{$n}";
        }

        return $code;
    }

    /** The nature a sewing SMV falls into: Basic (0–12), Semi Critical (12.01–20) … */
    public static function natureOf(float $smv): string
    {
        foreach (self::NATURE_BANDS as $nature => $top) {
            if ($smv <= $top) {
                return $nature;
            }
        }

        return 'HIGHLY_CRITICAL';
    }
}
