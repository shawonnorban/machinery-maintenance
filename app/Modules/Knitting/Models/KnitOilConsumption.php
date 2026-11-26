<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Daily Machine Oil Consumption: what a store handed out to the machines on a shift.
 */
class KnitOilConsumption extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'FINALIZED'];

    protected $table = 'knit_oil_consumptions';

    protected $fillable = ['company_id', 'cons_no', 'cons_date', 'shift_id', 'office_id', 'store_id', 'issued_by', 'remarks', 'status', 'finalized_at', 'finalized_by', 'created_by'];

    protected function casts(): array
    {
        return ['cons_date' => 'date', 'finalized_at' => 'datetime'];
    }
}
