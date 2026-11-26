<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One defect found on a roll, and how many of it.
 */
class KnitRollInspectionLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_roll_inspection_lines';

    protected $fillable = ['company_id', 'inspection_id', 'sort_order', 'defect', 'points_each', 'count', 'remarks'];

    protected function casts(): array
    {
        return ['points_each' => 'integer', 'count' => 'integer'];
    }
}
