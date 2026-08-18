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

    /**
     * Milliseconds are kept, so two transitions in the same second stay
     * distinguishable and the timeline reads in the order things happened.
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'work_order_id', 'from_status', 'to_status',
        'changed_by', 'changed_at', 'reason',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
