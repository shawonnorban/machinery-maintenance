<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One COA line of a GL source: a ledger it debits or credits, with an optional cost centre. */
class AccGlSourceLine extends BaseModel
{
    use BelongsToTenant;

    public const SIDES = ['DEBIT', 'CREDIT'];

    protected $table = 'acc_gl_source_lines';

    protected $fillable = ['company_id', 'gl_source_id', 'side', 'account_id', 'cost_center_id', 'note', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
