<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One machine-shift on the loading plan and the knit card booked on it.
 */
class KnitMachineLoad extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_machine_loads';

    protected $fillable = ['company_id', 'plan_date', 'shift_id', 'machine_id', 'card_id', 'plan_qty', 'created_by'];

    protected function casts(): array
    {
        return ['plan_date' => 'date', 'plan_qty' => 'float'];
    }
}
