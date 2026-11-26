<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Support;

use App\Modules\GatePass\Models\GatePassCategory;
use App\Modules\GatePass\Models\GatePassLocationType;
use App\Modules\GatePass\Models\GatePassPurpose;

/**
 * The gate pass lists a company starts with, as Brickland ships them (Gate
 * Pass → Master Data, AnnotechERP 11/01): the two categories, ten location
 * types and the common purposes. Made the first time a list is opened, and
 * only into an empty list; after that they are the company's to change.
 */
final class GatePassDefaults
{
    /** code => [name, kind, description] */
    public const CATEGORIES = [
        'ITEM' => ['Item Gate Pass (Product/Material)', 'ITEM', 'Products, materials or items leaving the factory.'],
        'MAIN' => ['Main Gate Pass (Employee)', 'MAIN', 'An employee or staff member leaving the factory.'],
    ];

    public const LOCATION_TYPES = [
        'LT-01' => 'Embroidery Factory', 'LT-02' => 'Printing Factory', 'LT-03' => 'Washing Factory', 'LT-04' => 'Finishing Factory',
        'LT-05' => 'Dyeing Factory', 'LT-06' => 'Testing Laboratory', 'LT-07' => 'Buyer Office', 'LT-08' => 'Supplier',
        'LT-09' => 'Subcontract Factory', 'LT-10' => 'Other',
    ];

    public const PURPOSES = [
        'PUR-01' => 'Embroidery', 'PUR-02' => 'Printing', 'PUR-03' => 'Washing', 'PUR-04' => 'Finishing', 'PUR-05' => 'Repair',
        'PUR-06' => 'Sample Approval', 'PUR-07' => 'Quality Testing', 'PUR-08' => 'Buyer Submission', 'PUR-09' => 'Subcontract',
        'PUR-10' => 'Supplier Return', 'PUR-11' => 'Machine Repair', 'PUR-12' => 'Other',
    ];

    public static function categories(string $companyId): void
    {
        if (GatePassCategory::query()->exists()) {
            return;
        }
        foreach (self::CATEGORIES as $code => [$name, $kind, $description]) {
            GatePassCategory::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'kind' => $kind, 'description' => $description, 'active' => true]);
        }
    }

    public static function locationTypes(string $companyId): void
    {
        if (GatePassLocationType::query()->exists()) {
            return;
        }
        foreach (self::LOCATION_TYPES as $code => $name) {
            GatePassLocationType::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'active' => true]);
        }
    }

    public static function purposes(string $companyId): void
    {
        if (GatePassPurpose::query()->exists()) {
            return;
        }
        foreach (self::PURPOSES as $code => $name) {
            GatePassPurpose::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'active' => true]);
        }
    }
}
