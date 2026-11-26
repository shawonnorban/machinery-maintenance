<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A specification's CM for one booking month, with the quantity and dates it was quoted on. Work Study → Costing (AnnotechERP 14/05).
 */
class WsCmRate extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_cm_rates';

    protected $fillable = ['company_id', 'spec_id', 'buyer_id', 'booking_month', 'order_qty', 'cm_per_dozen', 'budget_exp_pct', 'lead_time_days', 'confirm_date', 'ship_date', 'created_by'];

    protected function casts(): array
    {
        return ['order_qty' => 'integer', 'cm_per_dozen' => 'decimal:4', 'budget_exp_pct' => 'decimal:2', 'lead_time_days' => 'integer', 'confirm_date' => 'date', 'ship_date' => 'date'];
    }
}
