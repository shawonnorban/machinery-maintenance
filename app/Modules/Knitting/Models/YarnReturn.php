<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A return or exchange challan: yarn refused back to the supplier off an MRR.
 */
class YarnReturn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_returns';

    protected $fillable = ['company_id', 'return_no', 'receipt_id', 'supplier_id', 'return_date', 'type', 'challan_type', 'finalized', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'finalized' => 'boolean'];
    }
}
