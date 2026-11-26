<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

class ProductionLine extends BaseModel
{
    use BelongsToTenant;

    /** Company Management's Line Type options, as Brickland lists them. */
    public const TYPES = ['SEWING', 'CUTTING', 'FINISHING', 'QUALITY', 'SAMPLE', 'SPECIAL'];

    protected $table = 'production_lines';

    protected $fillable = [
        'company_id', 'department_id', 'section_id', 'floor_id', 'name', 'code', 'line_type',
        'manpower', 'capacity_per_day', 'target_per_day', 'status',
    ];
}
