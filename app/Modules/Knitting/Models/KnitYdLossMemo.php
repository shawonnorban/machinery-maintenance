<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A dyeing process-loss memo against a Y/D programme.
 */
class KnitYdLossMemo extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'APPROVED', 'CANCELLED'];

    protected $table = 'knit_yd_loss_memos';

    protected $fillable = ['company_id', 'memo_no', 'memo_date', 'program_id', 'supplier_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['memo_date' => 'date'];
    }
}
