<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One programme coming back on an out-house receipt, in kilos. Dye House → S/C Dyeing (AnnotechERP 17/09).
 */
class DyeScReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_sc_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'programme_id', 'qty_kg', 'sort_order'];

    protected function casts(): array
    {
        return ['qty_kg' => 'float'];
    }
}
