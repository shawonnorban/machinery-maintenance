<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time spent on a work order (ADR-050).
 *
 * Time only. Technicians are salaried, so an hour of theirs carries no cost of
 * its own; what these rows answer is workload and technician performance —
 * who did the work and how long it took.
 */
class WorkOrderLaborEntry extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'work_order_labor_entries';

    protected $fillable = [
        'company_id', 'work_order_id', 'technician_id',
        'started_at', 'ended_at', 'minutes', 'notes', 'recorded_by',
    ];

    /** Millisecond precision, matching the column (ERD rule 15). */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'minutes' => 'integer',
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
