<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A party's opening loan position on one item of a book (needle or oil), both directions.
 */
class KnitNeedleLoanOpening extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_loan_openings';

    protected $fillable = ['company_id', 'book', 'ob_date', 'party_id', 'item_id', 'uom', 'loan_received', 'loan_issued', 'store_id', 'cost_price', 'is_final', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['ob_date' => 'date', 'loan_received' => 'float', 'loan_issued' => 'float', 'cost_price' => 'float', 'is_final' => 'boolean'];
    }
}
