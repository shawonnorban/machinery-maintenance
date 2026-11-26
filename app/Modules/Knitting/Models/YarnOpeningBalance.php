<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A store's declared yarn opening balance, posted to the ledger when finalised.
 */
class YarnOpeningBalance extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_obs';

    protected $fillable = ['company_id', 'ob_no', 'ob_date', 'store_id', 'currency_id', 'buyer_id', 'order_id', 'is_buffer', 'remarks', 'finalized', 'created_by'];

    protected function casts(): array
    {
        return ['ob_date' => 'date', 'is_buffer' => 'boolean', 'finalized' => 'boolean'];
    }
}
