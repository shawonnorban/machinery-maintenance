<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shade band, what a kilo costs to run in it, and whether a lab dip comes first (Colour Group Rate Update). Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeColourGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_colour_groups';

    protected $fillable = ['company_id', 'code', 'name', 'labdip_required', 'water_cost', 'wash_cost', 'display_order', 'active'];

    protected function casts(): array
    {
        return ['labdip_required' => 'boolean', 'water_cost' => 'decimal:4', 'wash_cost' => 'decimal:4', 'display_order' => 'integer', 'active' => 'boolean'];
    }
}
