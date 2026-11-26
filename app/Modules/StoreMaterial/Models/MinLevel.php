<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The minimum stock a material is kept at; under it, the Low Stock Report lists it. Store & Material (AnnotechERP 09).
 */
class MinLevel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'sm_min_levels';

    protected $fillable = ['company_id', 'item_id', 'min_qty'];

    protected function casts(): array
    {
        return ['min_qty' => 'decimal:3'];
    }
}
