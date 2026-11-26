<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A day's pieces off the flat-bed machines against a requirement, rejects kept beside them.
 */
class KnitCcProduction extends BaseModel
{
    use BelongsToTenant;

    public const SOURCES = ['IN_HOUSE', 'SC_OUT_HOUSE'];

    protected $table = 'knit_cc_productions';

    protected $fillable = ['company_id', 'requirement_id', 'prod_date', 'source', 'floor_id', 'machine_id', 'shift_id', 'operator_id', 'program_id', 'qty', 'reject_qty', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['prod_date' => 'date', 'qty' => 'float', 'reject_qty' => 'float'];
    }
}
