<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What can be asked for, and how it is routed (Approval Center → Request Types
 * & Routing, AnnotechERP 04/03): its default priority and deadline, the chain
 * whose later levels follow Request To, and whom an overdue request goes to.
 */
class ApprovalRequestType extends BaseModel
{
    use BelongsToTenant;

    public const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];

    protected $table = 'approval_request_types';

    protected $fillable = [
        'company_id', 'code', 'name', 'module', 'default_priority', 'due_days', 'sort_order',
        'workflow_id', 'escalate_to_user_id', 'escalate_after_hours', 'description', 'active',
    ];

    protected function casts(): array
    {
        return ['due_days' => 'integer', 'sort_order' => 'integer', 'escalate_after_hours' => 'integer', 'active' => 'boolean'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function escalateTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalate_to_user_id');
    }
}
