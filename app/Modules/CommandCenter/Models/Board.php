<?php

declare(strict_types=1);

namespace App\Modules\CommandCenter\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A department's performance board. Command Center → Departmental Performance Boards (AnnotechERP 27/01).
 */
class Board extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'cc_boards';

    protected $fillable = ['company_id', 'name', 'department_id', 'owner_id', 'tiles', 'refresh', 'audience', 'data_health', 'published'];

    protected function casts(): array
    {
        return ['tiles' => 'integer', 'data_health' => 'float', 'published' => 'boolean'];
    }
}
