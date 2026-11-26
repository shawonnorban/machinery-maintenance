<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An order on a floor costed for one day: its SMV, FOB and per-piece rates. Work Study → Costing (AnnotechERP 14/05).
 */
class WsLineCosting extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_line_costings';

    protected $fillable = ['company_id', 'floor_id', 'cost_date', 'buyer_id', 'style_no', 'order_no', 'gmt_item', 'bgt_as_cm_pct', 'exc_rate', 'smv', 'fob', 'rate_cut', 'rate_sew', 'rate_fin'];

    protected function casts(): array
    {
        return ['cost_date' => 'date', 'bgt_as_cm_pct' => 'decimal:2', 'exc_rate' => 'decimal:4', 'smv' => 'decimal:2', 'fob' => 'decimal:4', 'rate_cut' => 'decimal:4', 'rate_sew' => 'decimal:4', 'rate_fin' => 'decimal:4'];
    }
}
