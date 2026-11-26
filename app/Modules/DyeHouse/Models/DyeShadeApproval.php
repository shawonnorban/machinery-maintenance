<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Rib & Collar Shade Approval: rib and collar shade checked against the body, per batch and position in the lot (SL#), grey and finished in weight and rolls. Dye House → Dyeing Q.C (AnnotechERP 17/08).
 */
class DyeShadeApproval extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['PENDING', 'PASSED', 'HOLD_DYEING', 'REJECTED'];

    protected $table = 'dye_shade_approvals';

    protected $fillable = ['company_id', 'batch_id', 'lot_no', 'sl_no', 'b_qty', 'rp_qty', 'rcv_date', 'aprv_date', 'buyer_id', 'order_id', 'colour_id', 'g_qty', 'g_roll', 'fin_qty', 'fin_roll', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['rcv_date' => 'date', 'aprv_date' => 'date', 'b_qty' => 'float', 'rp_qty' => 'float', 'g_qty' => 'float', 'fin_qty' => 'float', 'sl_no' => 'integer', 'g_roll' => 'integer', 'fin_roll' => 'integer'];
    }
}
