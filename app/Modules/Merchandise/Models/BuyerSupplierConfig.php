<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The suppliers a buyer nominates (Buyer-Wise Supplier Configuration). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class BuyerSupplierConfig extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'buyer_supplier_configs';

    protected $fillable = ['company_id', 'buyer_id', 'item_category_id', 'nominated_supplier_id', 'alternative_supplier_id', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
