<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One fabric and shade a booking asks the dye house for, in kilos. Dye House → Production Board (AnnotechERP 17/07).
 */
class DyeFabricBookingLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_fabric_booking_lines';

    protected $fillable = ['company_id', 'booking_id', 'fabric', 'colour_id', 'qty_kg', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['qty_kg' => 'float'];
    }
}
