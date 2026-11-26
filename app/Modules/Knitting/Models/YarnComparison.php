<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A yarn comparative statement: spinners' quotations against an approved indent. Knit Production → Yarn Procurement (AnnotechERP 15/02).
 */
class YarnComparison extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'FINALIZED', 'CANCELLED'];

    protected $table = 'knit_yarn_comparisons';

    protected $fillable = ['company_id', 'cs_no', 'cs_date', 'requisition_id', 'requisition_date', 'remarks', 'status', 'finalized_at', 'created_by'];

    protected function casts(): array
    {
        return ['cs_date' => 'date', 'requisition_date' => 'date', 'finalized_at' => 'datetime'];
    }
}
