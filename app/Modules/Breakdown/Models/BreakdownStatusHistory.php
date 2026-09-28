<?php

declare(strict_types=1);

namespace App\Modules\Breakdown\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Append-only (ERD rule 19).
 *
 * Absent in v1.0, which left the breakdown lifecycle as the only major workflow
 * with no state audit trail: nobody could answer who acknowledged a stoppage,
 * or when, after the fact.
 */
class BreakdownStatusHistory extends BaseModel
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'breakdown_status_histories';

    /** Milliseconds kept, so two changes in the same second stay ordered. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'company_id', 'breakdown_id', 'from_status', 'to_status',
        'changed_by', 'changed_at', 'reason',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }
}
