<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A leaf of a cheque book — deactivated, never deleted, once issued (Cheque Book Register). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccChequeLeaf extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'acc_cheque_leaves';

    protected $fillable = ['company_id', 'book_id', 'number', 'cheque_no', 'status', 'active'];

    protected function casts(): array
    {
        return ['number' => 'integer', 'active' => 'boolean'];
    }
}
