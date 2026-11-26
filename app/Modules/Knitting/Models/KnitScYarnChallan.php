<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Yarn a sub-contract party sent in to be knitted, on its challan.
 */
class KnitScYarnChallan extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'ISSUED', 'ACCEPTED', 'REJECTED', 'CANCELLED'];

    protected $table = 'knit_sc_yarn_challans';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'receipt_id', 'store_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
