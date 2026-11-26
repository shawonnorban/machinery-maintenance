<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A signed movement of one needle item in one store; a balance is their sum.
 */
class KnitNeedleMove extends BaseModel
{
    use BelongsToTenant;

    public const KINDS = ['OPENING', 'RECEIPT', 'RETURN', 'ISSUE_OUT', 'ISSUE_IN', 'LOAN_OUT', 'LOAN_BACK', 'LOAN_IN', 'CONSUME'];

    protected $table = 'knit_needle_moves';

    protected $fillable = ['company_id', 'book', 'store_id', 'item_id', 'move_date', 'kind', 'doc_no', 'doc_line_id', 'qty', 'value'];

    protected function casts(): array
    {
        return ['move_date' => 'date', 'qty' => 'float', 'value' => 'float'];
    }
}
