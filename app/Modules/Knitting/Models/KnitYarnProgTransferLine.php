<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One yarn lot going with a programme transfer.
 */
class KnitYarnProgTransferLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_prog_transfer_lines';

    protected $fillable = ['company_id', 'transfer_id', 'sort_order', 'composition_id', 'yarn_count_id', 'colour_group', 'shade', 'lot_no', 'bags', 'qty', 'uom', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'bags' => 'integer', 'sort_order' => 'integer'];
    }
}
