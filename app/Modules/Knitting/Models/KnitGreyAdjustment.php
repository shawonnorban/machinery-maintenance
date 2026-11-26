<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A physical-count correction to the grey store.
 */
class KnitGreyAdjustment extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'VERIFIED', 'APPROVED'];

    protected $table = 'knit_grey_adjustments';

    protected $fillable = ['company_id', 'adj_no', 'adj_date', 'store_id', 'reason', 'remarks', 'status', 'activity', 'created_by'];

    protected function casts(): array
    {
        return ['adj_date' => 'date'];
    }
}
