<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Yarn the party's store issues against one fabric serial.
 */
class KnitScReceiptYarn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_sc_receipt_yarns';

    protected $fillable = ['company_id', 'receipt_id', 'sort_order', 'sl', 'composition_id', 'yarn_count_id', 'fab_part', 'colour_group', 'brand', 'lot_no', 'stitch_length', 'elastane_pct', 'ratio_qty', 'required_qty', 'remarks'];

    protected function casts(): array
    {
        return ['sl' => 'integer', 'stitch_length' => 'float', 'elastane_pct' => 'float', 'ratio_qty' => 'float', 'required_qty' => 'float'];
    }
}
