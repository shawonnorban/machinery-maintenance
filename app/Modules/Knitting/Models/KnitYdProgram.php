<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A Y/D programme: greige yarn placed on a dye house, one line per shade to come back.
 */
class KnitYdProgram extends BaseModel
{
    use BelongsToTenant;

    public const TYPES = ['IN_HOUSE', 'SUB_CONTRACT'];

    public const STATUSES = ['RUNNING', 'COMPLETED', 'CLOSED', 'CANCELLED'];

    protected $table = 'knit_yd_programs';

    protected $fillable = ['company_id', 'program_no', 'program_date', 'yd_type', 'supplier_id', 'buyer_id', 'order_id', 'delivery_date', 'rev_no', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['program_date' => 'date', 'delivery_date' => 'date', 'rev_no' => 'integer'];
    }
}
