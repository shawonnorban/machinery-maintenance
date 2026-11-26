<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A dyeing machine on the floor — capacity, nozzles, make, origin and what it is doing (Dye Machine Profile). Dye House → Master Data (AnnotechERP 17/01).
 */
class DyeMachine extends BaseModel
{
    use BelongsToTenant;

    public const UNITS = ['KG', 'LBS'];

    public const PROD_STATUSES = ['FREE', 'BUSY', 'UNDER_MAINTENANCE', 'BREAKDOWN', 'RESERVED'];

    protected $table = 'dye_machines';

    protected $fillable = ['company_id', 'machine_no', 'model', 'machine_type_id', 'brand_id', 'location_id', 'origin_country_id', 'capacity', 'capacity_unit', 'efficiency', 'sl_no', 'year', 'rpm', 'temperature', 'nozzles', 'is_sample', 'floor_id', 'mc_version', 'prod_status', 'active'];

    protected function casts(): array
    {
        return ['capacity' => 'decimal:2', 'efficiency' => 'decimal:2', 'year' => 'integer', 'rpm' => 'integer', 'temperature' => 'integer', 'nozzles' => 'integer', 'is_sample' => 'boolean', 'active' => 'boolean'];
    }
}
