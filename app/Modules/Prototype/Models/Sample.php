<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sample, minted by the sample manager's approval of its requisition
 * (SMP-2026-00001), and the version it is on: each revision moves it to the
 * next one. Where it stands is a phase (requisition, development, revision,
 * quality, approval, handover) and a state within it.
 */
class Sample extends BaseModel
{
    use BelongsToTenant;

    public const PHASES = ['REQUISITION', 'DEVELOPMENT', 'REVISION', 'QUALITY', 'APPROVAL', 'HANDOVER'];

    /** Within a phase: Quality waits (PENDING) or FAILED; Development REWORK; Approval REJECTED or REVISION_REQUIRED; Handover READY → DELIVERED. */
    public const STATES = ['APPROVED', 'ASSIGNED', 'IN_PROGRESS', 'COMPLETED', 'PENDING', 'FAILED', 'REWORK', 'REJECTED', 'REVISION_REQUIRED', 'READY', 'DELIVERED', 'CANCELLED'];

    protected $table = 'proto_samples';

    protected $fillable = ['company_id', 'sample_no', 'requisition_id', 'version', 'phase', 'state', 'assigned_to', 'assigned_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'assigned_at' => 'datetime'];
    }

    public function isOpen(): bool
    {
        return $this->state !== 'CANCELLED';
    }
}
