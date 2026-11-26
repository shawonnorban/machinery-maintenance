<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A department head of the merchandising teams. Order Summary (AnnotechERP 32).
 */
class OsDepartmentHead extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'os_department_heads';

    protected $fillable = ['company_id', 'department_id', 'user_id'];

    protected function casts(): array
    {
        return [];
    }
}
