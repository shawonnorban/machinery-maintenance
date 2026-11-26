<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One shade written off on a process-loss memo.
 */
class KnitYdLossLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yd_loss_lines';

    protected $fillable = ['company_id', 'memo_id', 'sort_order', 'program_line_id', 'colour_group', 'lot_no', 'issued_qty', 'received_qty', 'remarks'];

    protected function casts(): array
    {
        return ['issued_qty' => 'float', 'received_qty' => 'float', 'sort_order' => 'integer'];
    }
}
