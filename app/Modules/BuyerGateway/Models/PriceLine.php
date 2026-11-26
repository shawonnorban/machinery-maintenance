<?php

declare(strict_types=1);

namespace App\Modules\BuyerGateway\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * An agreed price a buyer sees, by product, season and validity. Buyer Gateway → Buyer Price Schedules (AnnotechERP 25/03).
 */
class PriceLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bg_price_lines';

    protected $fillable = ['company_id', 'product', 'buyer_id', 'season', 'price', 'incoterm', 'min_qty', 'valid_from', 'valid_to'];

    protected function casts(): array
    {
        return ['price' => 'float', 'min_qty' => 'integer', 'valid_from' => 'date', 'valid_to' => 'date'];
    }
}
