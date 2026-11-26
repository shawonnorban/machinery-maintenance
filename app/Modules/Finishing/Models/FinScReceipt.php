<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Finished fabric taken back from a sub-contract processor into a store, against the challan it went out on; a draft until posted from the register, which credits the challan lines it answers. Finishing Line → S/C Process (AnnotechERP 18/03).
 */
class FinScReceipt extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'POSTED'];

    protected $table = 'fin_sc_receipts';

    protected $fillable = ['company_id', 'mrr_no', 'receive_date', 'challan_id', 'supplier_id', 'store_id', 'gate_pass_no', 'remarks', 'status', 'posted_at', 'posted_by', 'created_by'];

    protected function casts(): array
    {
        return ['receive_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinScReceiptLine::class, 'receipt_id')->orderBy('sort_order');
    }
}
