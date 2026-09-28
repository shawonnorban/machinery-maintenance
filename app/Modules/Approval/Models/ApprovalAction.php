<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One decision on one step. Append-only (ERD Section 20 rule 4).
 *
 * Every approval and every rejection is recorded with who and when, because
 * "who signed off the 200,000 taka repair" is the first question asked when one
 * turns out to have been unnecessary.
 */
class ApprovalAction extends BaseModel
{
    use BelongsToTenant;

    public const ACTIONS = ['APPROVED', 'REJECTED', 'DELEGATED', 'CANCELLED', 'EXPIRED'];

    protected $table = 'approval_actions';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'approval_request_id', 'approver_id', 'step',
        'action', 'comment', 'acted_at',
    ];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime', 'step' => 'integer'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }
}
