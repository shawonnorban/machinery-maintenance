<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Support;

use App\Modules\Merchandise\Models\SampleType;

/**
 * The sample types a company starts with (Prototype Desk → Sample Type
 * Configuration, AnnotechERP 07/01), on Brickland's defaults: pre-order, no
 * order or PO needed, buyer approval, QC, material, revision and delivery
 * required, seven days' lead. Created into an empty list the first time it is
 * opened; editing one marks it "Configured".
 */
final class SampleTypeDefaults
{
    /** code => [name, stage] */
    public const TYPES = [
        'PROTO' => ['Proto Sample', 'PRE_ORDER'],
        'FIT' => ['Fit Sample', 'PRE_ORDER'],
        'SIZESET' => ['Size Set Sample', 'PRE_ORDER'],
        'PP' => ['PP Sample', 'PRE_ORDER'],
        'TOP' => ['TOP Sample', 'PRE_ORDER'],
        'SHIP' => ['Shipment Sample', 'PRE_ORDER'],
        'DEV' => ['Development Sample', 'PRE_ORDER'],
        'PHOTO' => ['Photo Sample', 'PRE_ORDER'],
        'SALESMAN' => ['Salesman Sample', 'PRE_ORDER'],
        'COUNTER' => ['Counter Sample', 'PRE_ORDER'],
        'LABDIP' => ['Lab Dip', 'PRE_ORDER'],
    ];

    public static function ensure(string $companyId): void
    {
        if (SampleType::query()->exists()) {
            return;
        }
        foreach (self::TYPES as $code => [$name, $stage]) {
            SampleType::query()->create([
                'company_id' => $companyId, 'code' => $code, 'name' => $name, 'stage' => $stage,
                'order_required' => false, 'po_required' => false, 'buyer_approval_required' => true, 'qc_required' => true,
                'material_required' => true, 'revision_allowed' => true, 'delivery_required' => true, 'lead_days' => 7,
                'customised' => false, 'active' => true,
            ]);
        }
    }
}
