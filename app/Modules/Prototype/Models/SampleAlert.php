<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** An alert the Prototype Desk raises for a desk — read when seen, resolved when acted on (or by hand), reopened if it was not. */
class SampleAlert extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = [
        'SAMPLE_ASSIGNED', 'SAMPLE_OVERDUE', 'MATERIAL_REQUIRED', 'MATERIAL_SHORTAGE', 'MATERIAL_ISSUED', 'PRODUCTION_READY', 'QC_REQUIRED', 'APPROVAL_PENDING', 'FEEDBACK_PENDING',
        'REVISION_REQUIRED', 'TARGET_MISSED', 'CAPACITY_OVERLOAD', 'SAMPLE_COMPLETED', 'SAMPLE_APPROVED', 'HANDOVER_READY', 'SAMPLE_DELIVERED',
    ];

    public const SEVERITIES = ['CRITICAL', 'WARNING', 'INFO', 'SUCCESS'];

    public const DESKS = ['DESK', 'MERCHANDISING', 'SAMPLE_ROOM', 'STORE', 'PRODUCTION', 'QC'];

    protected $table = 'proto_alerts';

    protected $fillable = ['company_id', 'kind', 'severity', 'sample_id', 'params', 'for_desk', 'doc_no', 'raised_at', 'read_at', 'resolved_at', 'resolved_by'];

    protected function casts(): array
    {
        return ['params' => 'array', 'raised_at' => 'datetime', 'read_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
