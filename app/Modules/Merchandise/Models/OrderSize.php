<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One colour × size line of an order's breakdown; the lines add up to the order's quantity. */
class OrderSize extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_order_sizes';

    protected $fillable = ['company_id', 'order_id', 'colour', 'size', 'qty', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'sort_order' => 'integer'];
    }
}
