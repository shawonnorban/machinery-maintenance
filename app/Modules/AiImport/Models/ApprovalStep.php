<?php

declare(strict_types=1);

namespace App\Modules\AiImport\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sign-off an import needs before it writes to the ERP. AI File & PO Import → Import Approval Workflow (AnnotechERP 26/01).
 */
class ApprovalStep extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ai_approval_steps';

    protected $fillable = ['company_id', 'name', 'applies_to', 'approver', 'sequence', 'auto_skip', 'sla_hours', 'pending', 'enabled'];

    protected function casts(): array
    {
        return ['sequence' => 'integer', 'auto_skip' => 'float', 'sla_hours' => 'integer', 'pending' => 'integer', 'enabled' => 'boolean'];
    }
}
