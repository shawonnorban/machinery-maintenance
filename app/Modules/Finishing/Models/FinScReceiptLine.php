<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One line of a sub-contract receipt: the challan line it answers, what came back, the rolls and the short weight. Finishing Line → S/C Process (AnnotechERP 18/03).
 */
class FinScReceiptLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_sc_receipt_lines';

    protected $fillable = ['company_id', 'receipt_id', 'challan_line_id', 'description', 'lot_no', 'recv_kg', 'rolls', 'short_kg', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['recv_kg' => 'decimal:3', 'rolls' => 'integer', 'short_kg' => 'decimal:3', 'sort_order' => 'integer'];
    }
}
