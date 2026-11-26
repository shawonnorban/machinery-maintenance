<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

/**
 * The fixed lists Item/Combo/Model Registration picks from (AnnotechERP 06/05),
 * as Brickland offers them. The item types depend on the item group; "Other"
 * has none.
 */
final class StyleItemOptions
{
    public const GROUPS = ['KNIT_TOP', 'KNIT_BOTTOM', 'WOVEN_TOP', 'WOVEN_BOTTOM', 'OTHER'];

    /** @var array<string, list<string>> */
    public const TYPES = [
        'KNIT_TOP' => ['T-Shirt', 'Polo Shirt', 'Sweat Shirt', 'Tank Top', 'Jacket', 'Sweater', 'Jersey', 'Cap', 'Scarf', 'Coat', 'Night Top', 'Blouse', 'Tee Shirt', 'Boob Tube', 'Stripy', 'Nightwear', 'Bodysuit', 'Turtle Top', 'Tunic', 'Vest', 'Baby Body', 'Long Shirt', 'Bandeau Top', 'Sleep Shirt', 'Spaghetti Top', 'Basic Shirt', 'Ladies Top', 'Girls Top', 'Bardor Top', 'Sweat Top', 'Crop Top', 'Head Band', 'Mittens', 'Shirt', 'Hoodie', 'Bikini Top', 'Girls Bra'],
        'KNIT_BOTTOM' => ['Pant', 'Long Pant', 'Sweat Pant', 'Trouser', 'Pajama', 'Leggings', 'Jogging Pant', 'Capri Pant', 'Short Pyjama', 'Panty', 'Retro', 'Boxer', 'Brief', 'Underwear', 'Undershirt', 'Long Underpant', 'Skirt', 'Footie', 'Shorts', 'Long', 'Socks', 'Bikini Pants'],
        'WOVEN_TOP' => ['Sleep Shirt', 'Apron', 'Bikini Top', 'Jumpsuit', 'Peplum', 'Flounces', 'Cardigan', 'Neck Cover', 'Rompers', 'Bathrobe', 'Dog Dress'],
        'WOVEN_BOTTOM' => ['Cargo Short', 'Denim Pant', 'Chino Pant', 'Cargo Pant 3/4', 'Woven Pant', 'Woven Pant 3/4', 'Bikini Pants'],
        'OTHER' => [],
    ];

    public const DIVISIONS = ['MENS', 'WOMENS', 'LADIES', 'BOYS', 'GIRLS', 'CHILDREN', 'KIDS', 'NEWBORN', 'BABY', 'PRE_SCHOOL', 'SCHOOL', 'UNISEX'];

    public const NECK_TYPES = ['ROUND_NECK', 'V_NECK', 'POLO_COLLAR', 'HOODED', 'TURTLE_NECK'];

    public const SLEEVE_TYPES = ['SHORT_SLEEVE', 'LONG_SLEEVE', 'SLEEVELESS', 'THREE_QUARTER'];

    public const GENDERS = ['MEN', 'WOMEN', 'UNISEX', 'BOYS', 'GIRLS', 'KIDS', 'INFANT'];

    public const AGE_GROUPS = ['ADULT', 'TEEN', 'KIDS', 'TODDLER', 'BABY'];

    public const NATURE_OF_WORK = ['SEWING', 'SEWING_PRINT', 'SEWING_EMBROIDERY', 'CUT_MAKE'];

    public const CARE_SERVICES = ['SOLID', 'PRINT', 'EMBROIDERY', 'AOP', 'WASH', 'IRON', 'BLEACH', 'DRY_CLEAN', 'TUMBLE_DRY'];

    /** The service flags an item carries: the service type (solid, yarn dyed) and the services on it. */
    public const SERVICE_FLAGS = ['is_solid', 'is_yd', 'is_aop', 'is_gmt_wash', 'is_heat_seal', 'is_print', 'is_embroidery'];

    /** @return list<string> every type of every group */
    public static function allTypes(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::TYPES))));
    }
}
