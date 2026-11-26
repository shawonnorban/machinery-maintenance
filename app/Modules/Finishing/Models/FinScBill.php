<?php

declare(strict_types=1);

namespace App\Modules\Finishing\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A sub-contract processor's bill, on its own number, line by process against the challans it charges for; rewritten while draft or submitted, standing once completed. Finishing Line → S/C Process (AnnotechERP 18/03).
 */
class FinScBill extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'COMPLETED', 'CANCELLED'];

    protected $table = 'fin_sc_bills';

    protected $fillable = ['company_id', 'bill_no', 'bill_date', 'supplier_id', 'ref_bill_no', 'rev_no', 'rev_description', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date'];
    }

    public function lines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FinScBillLine::class, 'bill_id')->orderBy('sort_order');
    }
}
