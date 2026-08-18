<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A period during which work stopped for a stated reason (ADR-051).
 *
 * AWAITING_PARTS is the reason that earns this table its place: it turns a
 * spare-part shortage into its own measurable cause instead of letting it
 * inflate repair time and look like slow technicians.
 */
class WorkOrderHold extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'work_order_id', 'reason_code', 'notes',
        'started_at', 'ended_at', 'minutes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
        ];
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }
}
