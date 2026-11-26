<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A claim on a spinner to replace yarn this mill will not knit.
 */
class KnitYarnReplaceReq extends BaseModel
{
    use BelongsToTenant;

    /** Draft → Submitted → Issued (the yarn has physically left); Cancelled. */
    public const STATUSES = ['DRAFT', 'SUBMITTED', 'ISSUED', 'CANCELLED'];

    protected $table = 'knit_yarn_replace_reqs';

    protected $fillable = ['company_id', 'req_no', 'req_date', 'store_id', 'supplier_id', 'return_type', 'lc_no', 'lot_ref', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date'];
    }
}
