<?php

declare(strict_types=1);

namespace App\Modules\ProductionTracking\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A line's standard capacity (pieces per hour or per day), effective from a date (Line Capacity Master, 30/01). Production Tracking (AnnotechERP 30).
 */
class PtLineCapacity extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pt_line_capacities';

    protected $fillable = ['company_id', 'section', 'line_id', 'capacity_type', 'standard_capacity', 'default_efficiency', 'includes_efficiency', 'effective_from', 'effective_to', 'active', 'remarks', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['standard_capacity' => 'decimal:2', 'default_efficiency' => 'decimal:2', 'includes_efficiency' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date', 'active' => 'boolean'];
    }
}
