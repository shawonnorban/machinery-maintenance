<?php

declare(strict_types=1);

namespace App\Modules\SalesShipment\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A named attribute an order line can carry (Carton Dimension, Poly Thickness …). Sales & Shipment → Order & Delivery (AnnotechERP 21/02).
 */
class SsParticular extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ss_particulars';

    protected $fillable = ['company_id', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
