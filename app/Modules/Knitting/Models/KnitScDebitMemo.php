<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What an outside sub-contract party is charged back for.
 */
class KnitScDebitMemo extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'SETTLED', 'CANCELLED'];

    public const PARTS = ['FABRIC', 'COLLAR', 'CUFF', 'YARN'];

    protected $table = 'knit_sc_debit_memos';

    protected $fillable = ['company_id', 'memo_no', 'memo_date', 'party_id', 'currency_id', 'reason', 'remarks', 'total', 'status', 'approved_at', 'approved_by', 'created_by'];

    protected function casts(): array
    {
        return ['memo_date' => 'date', 'total' => 'float', 'approved_at' => 'datetime'];
    }
}
