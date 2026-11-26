<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One chemical dosed in a machine wash, in kilos, grams and milligrams as the operator read them, and their total in kilos. Dye House → Production Board (AnnotechERP 17/07).
 */
class DyeWashProductionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_wash_production_lines';

    protected $fillable = ['company_id', 'production_id', 'item_id', 'kg', 'gm', 'mg', 'total_kg', 'sort_order'];

    protected function casts(): array
    {
        return ['kg' => 'float', 'gm' => 'float', 'mg' => 'float', 'total_kg' => 'float'];
    }
}
