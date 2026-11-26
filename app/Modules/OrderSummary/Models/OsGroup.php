<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A column group of the sheet (01. Style Detail …). Order Summary (AnnotechERP 32).
 */
class OsGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'os_groups';

    protected $fillable = ['company_id', 'code', 'name', 'sort_order', 'tone', 'is_system', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_system' => 'boolean', 'active' => 'boolean'];
    }
}
