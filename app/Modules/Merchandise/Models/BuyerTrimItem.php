<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A trim or accessory a buyer specifies — label, tag, button … (Buyer-Wise Trims & Accessories). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerTrimItem extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'buyer_trim_items';

    protected $fillable = ['company_id', 'buyer_id', 'item_code', 'item_name', 'category', 'item_category_id', 'specification', 'material', 'color', 'size', 'uom', 'description', 'approved_by', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
