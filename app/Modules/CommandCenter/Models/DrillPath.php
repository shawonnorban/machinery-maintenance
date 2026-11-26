<?php

declare(strict_types=1);

namespace App\Modules\CommandCenter\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How a headline metric opens up. Command Center → Metric Drill-down Explorer (AnnotechERP 27/01).
 */
class DrillPath extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'cc_drill_paths';

    protected $fillable = ['company_id', 'metric', 'domain', 'level_1', 'level_2', 'level_3', 'source', 'grain', 'rows_k'];

    protected function casts(): array
    {
        return ['rows_k' => 'integer'];
    }
}
