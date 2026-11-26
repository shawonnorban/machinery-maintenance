<?php

declare(strict_types=1);

namespace App\Modules\Planning\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A default efficiency for a style, buyer, line or unit from an effective date (Efficiency Master). Capacity Planning → Master Data (AnnotechERP 12/01).
 */
class PlanningEfficiencyStandard extends BaseModel
{
    use BelongsToTenant;

    /** Narrowest first: a style standard wins over a buyer's, a buyer's over a line's, a line's over a unit's. */
    public const APPLIES_TO = ['STYLE', 'BUYER', 'LINE', 'UNIT'];

    protected $table = 'planning_efficiency_standards';

    protected $fillable = ['company_id', 'applies_to', 'factory_id', 'planning_line_id', 'buyer_id', 'style_no', 'efficiency', 'effective_from', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['efficiency' => 'decimal:2', 'effective_from' => 'date', 'active' => 'boolean'];
    }
}
