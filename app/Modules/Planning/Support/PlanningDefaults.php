<?php

declare(strict_types=1);

namespace App\Modules\Planning\Support;

use App\Modules\Planning\Models\PlanningLeadTime;

/**
 * The lead times a company's master plans start from, as Brickland ships them
 * (Capacity Planning → Other Setup, AnnotechERP 12/01). Made the first time
 * the list is opened; after that they are the company's to change.
 */
final class PlanningDefaults
{
    /** activity => [lead days, counted from] */
    public const LEAD_TIMES = [
        'FABRIC_BOOKING' => [5, 'BEFORE_CUTTING'],
        'FABRIC_INHOUSE' => [10, 'BEFORE_CUTTING'],
        'ACCESSORIES_BOOKING' => [5, 'BEFORE_CUTTING'],
        'ACCESSORIES_INHOUSE' => [7, 'BEFORE_CUTTING'],
        'CUTTING' => [2, 'BEFORE_FIRST_SEWING'],
        'FINISHING' => [1, 'AFTER_LAST_SEWING'],
        'PACKING' => [1, 'AFTER_LAST_SEWING'],
    ];

    public static function leadTimes(string $companyId): void
    {
        if (PlanningLeadTime::query()->exists()) {
            return;
        }
        $order = 0;
        foreach (self::LEAD_TIMES as $activity => [$days, $from]) {
            PlanningLeadTime::query()->create(['company_id' => $companyId, 'activity' => $activity, 'lead_days' => $days, 'counted_from' => $from, 'sort_order' => $order += 10, 'changed' => false]);
        }
    }
}
