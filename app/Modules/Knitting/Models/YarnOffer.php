<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One spinner's price against one line of a comparative statement, in one price band (local or import).
 */
class YarnOffer extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_offers';

    protected $fillable = ['company_id', 'comparison_id', 'sl_no', 'source', 'supplier_id', 'price', 'currency_id', 'conf_qty', 'lead_days', 'remarks'];

    protected function casts(): array
    {
        return ['price' => 'float', 'conf_qty' => 'float', 'lead_days' => 'integer'];
    }
}
