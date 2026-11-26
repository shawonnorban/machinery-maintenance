<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What the buyer said about a sample (FB-2026-00001), the corrections asked
 * for and who has to act on them. A closed feedback is locked.
 */
class SampleFeedback extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['APPROVAL', 'COMMENT', 'CORRECTION', 'REJECTION', 'GENERAL'];

    public const STATUSES = ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'];

    protected $table = 'proto_feedback';

    protected $fillable = [
        'company_id', 'feedback_no', 'sample_id', 'version', 'approval_id', 'feedback_date', 'feedback_type', 'status', 'comment',
        'measurement_change', 'fabric_change', 'color_change', 'construction_change', 'action_required', 'assigned_to', 'due_date', 'attachment',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer', 'feedback_date' => 'date', 'due_date' => 'date', 'action_required' => 'boolean'];
    }
}
