<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Support;

use App\Modules\WorkStudy\Models\WsGarmentItem;
use App\Modules\WorkStudy\Models\WsResponsibleDept;

/**
 * The Work Study lists a company starts with, as Brickland ships them
 * (AnnotechERP 14/01): four garment items and five responsible departments.
 * Made the first time a list is opened, and only into an empty list.
 */
final class WorkStudyDefaults
{
    /** group => items */
    public const GARMENT_ITEMS = ['Knit Tops' => ['Basic T-Shirt', 'Polo Shirt', 'Pullover Hoodie'], 'Bottoms' => ['Twill Trouser']];

    /** The garment categories a line may be preferred for, as Brickland lists them. */
    public const GMT_CATEGORIES = ['Knit Tops', 'Bottoms', 'Outerwear'];

    /** name => [code, sl] */
    public const DEPARTMENTS = ['Industrial Engineering' => ['IE', 10], 'Production' => ['PROD', 20], 'Quality' => ['QA', 30], 'Maintenance' => ['MAINT', 40], 'Planning' => ['PLAN', 50]];

    public static function garmentItems(string $companyId): void
    {
        if (WsGarmentItem::query()->exists()) {
            return;
        }
        foreach (self::GARMENT_ITEMS as $group => $items) {
            foreach ($items as $name) {
                WsGarmentItem::query()->create(['company_id' => $companyId, 'item_group' => $group, 'name' => $name, 'active' => true]);
            }
        }
    }

    public static function departments(string $companyId): void
    {
        if (WsResponsibleDept::query()->exists()) {
            return;
        }
        foreach (self::DEPARTMENTS as $name => [$code, $sl]) {
            WsResponsibleDept::query()->create(['company_id' => $companyId, 'name' => $name, 'code' => $code, 'sl' => $sl]);
        }
    }
}
