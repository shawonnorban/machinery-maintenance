<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A change to a sample after feedback or correction: a new version that
 * never replaces the last. Edited only while Open; never removed.
 */
class SampleRevision extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['OPEN', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];

    public const CHANGES = ['changed_measurement', 'changed_fabric', 'changed_color', 'changed_accessories', 'changed_construction'];

    protected $table = 'proto_revisions';

    protected $fillable = [
        'company_id', 'rev_no', 'sample_id', 'previous_version', 'new_version', 'revision_date', 'due_date', 'status', 'reason', 'source_approval_id', 'source_feedback_id',
        'buyer_comment', 'internal_comment', 'changed_measurement', 'changed_fabric', 'changed_color', 'changed_accessories', 'changed_construction',
        'requested_by', 'approved_by', 'approved_at', 'attachment',
    ];

    protected function casts(): array
    {
        return ['previous_version' => 'integer', 'new_version' => 'integer', 'revision_date' => 'date', 'due_date' => 'date', 'approved_at' => 'datetime'];
    }
}
