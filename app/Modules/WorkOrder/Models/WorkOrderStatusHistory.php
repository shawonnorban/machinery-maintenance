<?php

declare(strict_types=1);

namespace App\Modules\WorkOrder\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** Append-only (ERD rule 19). */
class WorkOrderStatusHistory extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'work_order_status_histories';

    protected $fillable = [
        'company_id', 'work_order_id', 'from_status', 'to_status',
        'changed_by', 'changed_at', 'reason',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
