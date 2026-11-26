<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Standard minutes for a style — sewing SMV and whole-garment SAM (SMV / SAM Master). Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class PlanningStyleSmv extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'planning_style_smvs';

    protected $fillable = ['company_id', 'style_no', 'smv', 'sam', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['smv' => 'decimal:2', 'sam' => 'decimal:2', 'active' => 'boolean'];
    }
}
