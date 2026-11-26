<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Someone notified when a TNA event is raised, beyond the owning desk. Access Control → TNA Task Status Setup (AnnotechERP 02/04).
 */
class TnaEventUser extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tna_event_users';

    protected $fillable = ['company_id', 'tna_task_id', 'user_id'];
}
