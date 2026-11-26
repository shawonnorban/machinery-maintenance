<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One trim booked on an accessory booking (Accessories & Trims, AnnotechERP 06/08). */
class AccBookingLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_acc_booking_lines';

    protected $fillable = ['company_id', 'booking_id', 'item_id', 'description', 'colour', 'size', 'qty', 'uom', 'rate', 'status', 'sort_order'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rate' => 'float', 'sort_order' => 'integer'];
    }
}
