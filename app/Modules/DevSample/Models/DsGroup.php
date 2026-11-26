<?php

declare(strict_types=1);

namespace App\Modules\DevSample\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A column group of the Dev Sample sheet (01. Basic Information …). Dev Sample Status Column Setup (2026-11-21).
 */
class DsGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ds_groups';

    protected $fillable = ['company_id', 'code', 'name', 'sort_order', 'tone', 'is_system', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_system' => 'boolean', 'active' => 'boolean'];
    }
}
