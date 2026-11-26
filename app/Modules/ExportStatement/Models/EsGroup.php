<?php

declare(strict_types=1);

namespace App\Modules\ExportStatement\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A column group of the Export Statement sheet (01. Invoice & Order …). Export Statement Column Setup (2026-11-21).
 */
class EsGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'es_groups';

    protected $fillable = ['company_id', 'code', 'name', 'sort_order', 'tone', 'is_system', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_system' => 'boolean', 'active' => 'boolean'];
    }
}
