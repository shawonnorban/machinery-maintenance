<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One charged item on a debit memo.
 */
class KnitScDebitMemoLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_sc_debit_memo_lines';

    protected $fillable = ['company_id', 'memo_id', 'sort_order', 'part', 'order_id', 'colour_id', 'colour_name', 'description', 'challan_no', 'qty', 'uom', 'rate', 'amount', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'amount' => 'float', 'sort_order' => 'integer'];
    }
}
