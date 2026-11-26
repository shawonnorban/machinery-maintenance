<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One round of judging a received bundle: Pass, Reject and Rework adding up to what was inspected, with the defect; the rework followed up to completion and then inspected again in the next round. Assembly Floor → Quality Management (AnnotechERP 20/03).
 */
class AsmQualityInspection extends BaseModel
{
    use BelongsToTenant;

    public const REWORK_STATUSES = ['PENDING', 'PROCESSING', 'COMPLETED'];

    /** Brickland's defect list, in its order. */
    public const DEFECT_TYPES = ['STITCHING', 'MEASUREMENT', 'FABRIC', 'SEWING', 'PRINTING', 'EMBROIDERY', 'WASHING', 'FINISHING', 'OTHER'];

    protected $table = 'asm_quality_inspections';

    protected $fillable = [
        'company_id', 'quality_in_id', 'round', 'inspected_on', 'inspected_qty', 'pass_qty', 'reject_qty', 'rework_qty', 'defect_type', 'remark',
        'rework_status', 'rework_started_at', 'rework_started_by', 'rework_completed_at', 'rework_completed_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inspected_on' => 'date', 'round' => 'integer', 'inspected_qty' => 'integer', 'pass_qty' => 'integer', 'reject_qty' => 'integer', 'rework_qty' => 'integer',
            'rework_started_at' => 'datetime', 'rework_completed_at' => 'datetime',
        ];
    }
}
