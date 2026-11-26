<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A shift's needle breakage, machine by machine.
 */
class KnitNeedleBroken extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'CONFIRMED', 'CANCELLED'];

    protected $table = 'knit_needle_brokens';

    protected $fillable = ['company_id', 'ref_no', 'broken_date', 'shift_id', 'office_id', 'machine_type_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['broken_date' => 'date'];
    }
}
