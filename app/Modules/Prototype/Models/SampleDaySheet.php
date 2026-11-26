<?php

declare(strict_types=1);

namespace App\Modules\Prototype\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The sample room's sheet for one day: its capacity (available manpower ×
 * shift hours) and what each sample line is asked to produce. One per date;
 * a closed one is locked.
 */
class SampleDaySheet extends BaseModel
{
    use BelongsToTenant;

    public const SHIFTS = ['DAY', 'NIGHT'];

    public const STATES = ['DRAFT', 'CONFIRMED', 'CLOSED'];

    protected $table = 'proto_day_sheets';

    protected $fillable = ['company_id', 'target_date', 'sample_room', 'shift', 'unit_company_id', 'supervisor_id', 'prepared_by', 'state', 'total_manpower', 'available_manpower', 'shift_hours', 'remarks'];

    protected function casts(): array
    {
        return ['target_date' => 'date', 'total_manpower' => 'integer', 'available_manpower' => 'integer', 'shift_hours' => 'decimal:2'];
    }
}
