<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What the twister is owed.
 */
class KnitTwistBill extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'PAID', 'CANCELLED'];

    protected $table = 'knit_twist_bills';

    protected $fillable = ['company_id', 'bill_no', 'bill_date', 'supplier_id', 'program_id', 'party_bill_no', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date'];
    }
}
