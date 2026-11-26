<?php

declare(strict_types=1);

namespace App\Modules\StoreMaterial\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Stock Adjustment: a physical count against the system's, reaching the books when the Store Manager approves. Store & Material (AnnotechERP 09).
 */
class Adjustment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'sm_adjustments';

    protected $fillable = ['company_id', 'sa_no', 'sa_date', 'stock_id', 'system_qty', 'physical_qty', 'reason', 'remarks', 'status', 'requested_by', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['sa_date' => 'date', 'system_qty' => 'decimal:3', 'physical_qty' => 'decimal:3', 'decided_at' => 'datetime'];
    }
}
