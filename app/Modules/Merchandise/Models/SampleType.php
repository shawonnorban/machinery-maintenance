<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A kind of sample and what it requires — stage, order, PO, QC, material, buyer approval, revision, delivery (Prototype Desk → Sample Type Configuration). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class SampleType extends BaseModel
{
    use BelongsToTenant;

    public const STAGES = ['PRE_ORDER', 'ORDER', 'PRE_PRODUCTION', 'BULK', 'POST_PRODUCTION'];

    protected $table = 'sample_types';

    protected $fillable = ['company_id', 'code', 'name', 'stage', 'order_required', 'po_required', 'buyer_approval_required', 'qc_required', 'material_required', 'revision_allowed', 'delivery_required', 'lead_days', 'customised', 'active'];

    protected function casts(): array
    {
        return ['order_required' => 'boolean', 'po_required' => 'boolean', 'buyer_approval_required' => 'boolean', 'qc_required' => 'boolean', 'material_required' => 'boolean', 'revision_allowed' => 'boolean', 'delivery_required' => 'boolean', 'lead_days' => 'integer', 'customised' => 'boolean', 'active' => 'boolean'];
    }
}
