<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One approval level's decision on a sample requisition; never edited or removed. */
class SampleRequisitionDecision extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'proto_requisition_decisions';

    protected $fillable = ['company_id', 'requisition_id', 'level', 'decision', 'decided_by', 'decided_at', 'comments', 'rejection_reason', 'attachment'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }
}
