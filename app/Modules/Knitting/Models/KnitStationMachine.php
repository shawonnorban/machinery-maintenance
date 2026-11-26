<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A machine a station shows as a tab.
 */
class KnitStationMachine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_station_machines';

    protected $fillable = ['company_id', 'station_id', 'machine_id', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
