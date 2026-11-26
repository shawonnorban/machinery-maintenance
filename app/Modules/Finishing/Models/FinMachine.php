<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A finishing machine's register entry: type, capacity, status and the floor it stands on (Finishing Machine Profile). Finishing Line → Master Data (AnnotechERP 18/01).
 */
class FinMachine extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['STENTER', 'COMPACTOR', 'DRYER', 'SLITTING', 'HYDRO_EXTRACTOR', 'SQUEEZER', 'BRUSH', 'CALENDER', 'TUBE_HEAT_SET'];

    public const STATUSES = ['RUNNING', 'IDLE', 'UNDER_MAINTENANCE', 'BREAKDOWN'];

    /** Brickland's units of measure, as the MOU box offers them. */
    public const UNITS = ['BAG', 'COIL', 'CTN', 'DZN', 'GRS', 'KG', 'LTR', 'MTR', 'PCS', 'ROLL', 'SET', 'YDS'];

    protected $table = 'fin_machines';

    protected $fillable = ['company_id', 'machine_no', 'name', 'model', 'origin_country_id', 'brand_id', 'capacity', 'location_id', 'mou', 'efficiency', 'sl_no', 'machine_type', 'rpm', 'year', 'status', 'temperature', 'floor_id', 'is_sample', 'key_image', 'active'];

    protected function casts(): array
    {
        return ['capacity' => 'decimal:2', 'efficiency' => 'decimal:2', 'rpm' => 'integer', 'year' => 'integer', 'temperature' => 'integer', 'is_sample' => 'boolean', 'active' => 'boolean'];
    }
}
