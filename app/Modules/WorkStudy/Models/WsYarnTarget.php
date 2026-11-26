<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The yarn overrun a department is allowed in a month. Work Study → Target & Feeding (AnnotechERP 14/03).
 */
class WsYarnTarget extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_yarn_targets';

    protected $fillable = ['company_id', 'period', 'department_id', 'target_qty'];

    protected function casts(): array
    {
        return ['target_qty' => 'decimal:2'];
    }
}
