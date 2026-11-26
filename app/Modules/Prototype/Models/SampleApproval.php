<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A QC-passed sample put up for merchandising review or buyer approval
 * (APR-2026-00001), and the decision taken on it. A decided one is locked.
 */
class SampleApproval extends BaseModel
{
    use BelongsToTenant;

    public const STEPS = ['MERCHANDISING_REVIEW', 'BUYER_APPROVAL'];

    public const DECISIONS = ['PENDING', 'APPROVED', 'APPROVED_WITH_COMMENT', 'REJECTED', 'REVISION_REQUIRED'];

    public const APPROVED = ['APPROVED', 'APPROVED_WITH_COMMENT'];

    protected $table = 'proto_approvals';

    protected $fillable = ['company_id', 'approval_no', 'sample_id', 'version', 'quality_check_id', 'step', 'submitted_date', 'decision', 'approval_date', 'approval_by', 'buyer_comment', 'internal_comment', 'attachment', 'decided_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'submitted_date' => 'date', 'approval_date' => 'date'];
    }

    public function isDecided(): bool
    {
        return $this->decision !== 'PENDING';
    }
}
