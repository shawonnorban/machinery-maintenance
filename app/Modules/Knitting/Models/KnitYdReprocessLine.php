<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One lot going back for re-dyeing.
 */
class KnitYdReprocessLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yd_reprocess_lines';

    protected $fillable = ['company_id', 'reprocess_id', 'sort_order', 'lot_id', 'composition_id', 'yarn_count_id', 'colour_group', 'lot_no', 'qty', 'uom', 'reason', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'sort_order' => 'integer'];
    }
}
