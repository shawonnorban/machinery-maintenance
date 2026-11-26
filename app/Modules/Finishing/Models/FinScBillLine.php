<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One process charged on a sub-contract bill: the challan it was done under, kilos, the party's rate and the amount, recomputed on save. Finishing Line → S/C Process (AnnotechERP 18/03).
 */
class FinScBillLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fin_sc_bill_lines';

    protected $fillable = ['company_id', 'bill_id', 'challan_id', 'process_id', 'description', 'qty_kg', 'rate', 'amount', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['qty_kg' => 'decimal:3', 'rate' => 'decimal:2', 'amount' => 'decimal:2', 'sort_order' => 'integer'];
    }
}
