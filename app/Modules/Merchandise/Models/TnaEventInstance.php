<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A TNA event raised against a style: its owner, plan and actual dates, and where it stands. Access Control → TNA Task Status Setup (AnnotechERP 02/04).
 */
class TnaEventInstance extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tna_event_instances';

    protected $fillable = ['company_id', 'tna_task_id', 'reference', 'owner_id', 'plan_date', 'actual_date', 'status'];

    protected function casts(): array
    {
        return ['plan_date' => 'date', 'actual_date' => 'date'];
    }
}
