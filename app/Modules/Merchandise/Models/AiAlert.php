<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A finding raised against an order; it outlives the run, so its status stays as set (Merchandise AI, AnnotechERP 06/02–06/04). */
class AiAlert extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['OPEN', 'ACKNOWLEDGED', 'RESOLVED', 'DISMISSED'];

    protected $table = 'merch_ai_alerts';

    protected $fillable = ['company_id', 'order_id', 'kind', 'severity', 'title', 'detail', 'advice', 'status', 'first_run_id', 'last_run_id', 'status_at', 'status_by'];

    protected function casts(): array
    {
        return ['status_at' => 'datetime'];
    }
}
