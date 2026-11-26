<?php

declare(strict_types=1);

namespace App\Modules\GeneralStore\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A department the store issues to and takes requisitions from (Department). General Store → Master Data (AnnotechERP 10/01).
 */
class GsDepartment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gs_departments';

    protected $fillable = ['company_id', 'code', 'name', 'description', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
