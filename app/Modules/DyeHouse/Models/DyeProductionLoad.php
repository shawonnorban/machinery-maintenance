<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One load of a production: the machine, when it went on and came off, the type of run and where it stands. Dye House → Batch (Sample) (AnnotechERP 17/04).
 */
class DyeProductionLoad extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['LOADED', 'RUNNING', 'UNLOADED', 'ABORTED'];

    protected $table = 'dye_production_loads';

    protected $fillable = ['company_id', 'production_id', 'dye_machine_id', 'load_time', 'unload_time', 'check_roll_at', 'load_type', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['load_time' => 'datetime', 'unload_time' => 'datetime', 'check_roll_at' => 'datetime'];
    }
}
