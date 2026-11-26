<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A request to change a locked master plan, and the planning manager's decision. Capacity Planning → Revision Request (AnnotechERP 12/02).
 */
class PlanningRevisionRequest extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED'];

    protected $table = 'planning_revision_requests';

    protected $fillable = [
        'company_id', 'master_plan_id', 'reason', 'status', 'requested_by', 'decided_by', 'decided_at', 'decision_note', 'revised_plan_id',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }
}
