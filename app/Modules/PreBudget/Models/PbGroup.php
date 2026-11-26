<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A column group of the requisition sheet (01. Order …). Pre-Budget (AnnotechERP 33/34).
 */
class PbGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pb_groups';

    protected $fillable = ['company_id', 'sheet', 'code', 'name', 'sort_order', 'tone', 'is_system', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_system' => 'boolean', 'active' => 'boolean'];
    }
}
