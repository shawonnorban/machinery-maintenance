<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A user allowed to see a T&A task, and what more they may do with it (TNA Task Rights). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class TnaTaskRight extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'tna_task_rights';

    protected $fillable = ['company_id', 'user_id', 'tna_task_id', 'can_create', 'can_update', 'can_delete'];

    protected function casts(): array
    {
        return ['can_create' => 'boolean', 'can_update' => 'boolean', 'can_delete' => 'boolean'];
    }
}
