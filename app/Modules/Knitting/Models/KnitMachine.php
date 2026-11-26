<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A knitting machine on the floor: type, make, origin, diameter and gauges (Knitting Machine Profile). Knit Production → Master Data (AnnotechERP 15/01).
 */
class KnitMachine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_machines';

    protected $fillable = ['company_id', 'machine_no', 'machine_type_id', 'brand_id', 'model', 'origin_country_id', 'serial_no', 'dia_id', 'gauge_id', 'supported_gauge_ids', 'capacity_kg', 'feeders', 'rpm', 'efficiency', 'floor_id', 'mfg_year', 'active'];

    protected function casts(): array
    {
        return ['supported_gauge_ids' => 'array', 'capacity_kg' => 'decimal:2', 'feeders' => 'integer', 'rpm' => 'integer', 'efficiency' => 'decimal:2', 'mfg_year' => 'integer', 'active' => 'boolean'];
    }
}
