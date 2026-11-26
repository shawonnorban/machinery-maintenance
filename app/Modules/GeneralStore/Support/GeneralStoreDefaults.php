<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Support;

use App\Modules\GeneralStore\Models\GsCategory;
use App\Modules\GeneralStore\Models\GsDepartment;
use App\Modules\GeneralStore\Models\GsStore;
use App\Modules\GeneralStore\Models\GsUnit;
use Illuminate\Database\Eloquent\Model;

/**
 * The general store lists a company starts with, as Brickland ships them
 * (General Store → Master Data, AnnotechERP 10/01): ten categories, fourteen
 * units, twelve departments and five stores, Main the default. Made the first
 * time a list is opened, and only into an empty list; after that they are the
 * company's to change.
 */
final class GeneralStoreDefaults
{
    public const CATEGORIES = [
        'CLEAN' => 'Cleaning', 'ELEC' => 'Electrical', 'HARD' => 'Hardware', 'IT' => 'IT Accessories', 'MAINT' => 'Maintenance',
        'OFFICE' => 'Office Supplies', 'OTHER' => 'Others', 'PACK' => 'Packaging', 'SAFE' => 'Safety', 'STAT' => 'Stationery',
    ];

    public const UNITS = [
        'BOX' => 'Box', 'CTN' => 'Carton', 'DZN' => 'Dozen', 'FT' => 'Feet', 'GM' => 'Gram', 'KG' => 'Kilogram', 'LTR' => 'Litre',
        'MTR' => 'Meter', 'PAIR' => 'Pair', 'PCS' => 'Pieces', 'PKT' => 'Packet', 'REAM' => 'Ream', 'ROLL' => 'Roll', 'SET' => 'Set',
    ];

    public const DEPARTMENTS = [
        'ACC' => 'Accounts', 'ADMIN' => 'Admin', 'COM' => 'Commercial', 'CUT' => 'Cutting', 'FIN' => 'Finishing', 'HR' => 'HR',
        'IT' => 'IT', 'MAINT' => 'Maintenance', 'PROD' => 'Production', 'QA' => 'Quality', 'SEW' => 'Sewing', 'STORE' => 'Store',
    ];

    /** code => [name, default?] */
    public const STORES = [
        'ELEC' => ['Electrical Store', false], 'MAIN' => ['Main Store', true], 'MAINT' => ['Maintenance Store', false],
        'PACK' => ['Packaging Store', false], 'SAFE' => ['Safety Store', false],
    ];

    public static function categories(string $companyId): void
    {
        self::fill(GsCategory::class, $companyId, self::CATEGORIES);
    }

    /**
     * Units are the one shared list (`units_of_measure`): the platform's are
     * already there, so a company's first visit adds only Brickland's units
     * the platform lacks (Carton, Gram, Packet …).
     */
    public static function units(string $companyId): void
    {
        if (GsUnit::query()->where('company_id', $companyId)->exists()) {
            return;
        }
        $have = GsUnit::query()->pluck('code')->map(fn ($c) => strtoupper((string) $c))->all();
        foreach (self::UNITS as $code => $name) {
            if (! in_array($code, $have, true)) {
                GsUnit::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'active' => true]);
            }
        }
    }

    public static function departments(string $companyId): void
    {
        self::fill(GsDepartment::class, $companyId, self::DEPARTMENTS);
    }

    public static function stores(string $companyId): void
    {
        if (GsStore::query()->exists()) {
            return;
        }
        foreach (self::STORES as $code => [$name, $default]) {
            GsStore::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'is_default' => $default, 'active' => true]);
        }
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, string>  $rows
     */
    private static function fill(string $model, string $companyId, array $rows): void
    {
        if ($model::query()->exists()) {
            return;
        }
        foreach ($rows as $code => $name) {
            $model::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'active' => true]);
        }
    }
}
