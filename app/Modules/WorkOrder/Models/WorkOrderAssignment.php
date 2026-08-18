<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderAssignment extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'work_order_id', 'technician_id',
        'assigned_by', 'assigned_at', 'unassigned_at',
    ];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'unassigned_at' => 'datetime'];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }
}
