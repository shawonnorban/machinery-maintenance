<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A Knit Programme (KP) released to the floor against a production order.
 */
class KnitProgram extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['OPEN', 'RUNNING', 'COMPLETED', 'CLOSED', 'CANCELLED'];

    protected $table = 'knit_programs';

    protected $fillable = ['company_id', 'kp_no', 'prod_order_id', 'program_date', 'qty', 'floor_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['program_date' => 'date', 'qty' => 'float'];
    }
}
