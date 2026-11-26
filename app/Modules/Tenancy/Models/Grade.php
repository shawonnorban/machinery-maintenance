<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A pay/seniority grade (Company Management → Designation & Grade). `sort_order`
 * is the "Order" column: grades are ranked, not alphabetical.
 */
class Grade extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'grades';

    protected $fillable = ['company_id', 'name', 'code', 'code_bn', 'sort_order', 'status'];
}
