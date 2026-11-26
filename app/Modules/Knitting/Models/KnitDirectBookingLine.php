<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One fabric in one shade on a direct booking.
 */
class KnitDirectBookingLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_direct_booking_lines';

    protected $fillable = ['company_id', 'booking_id', 'sort_order', 'fabric_type_id', 'fabric', 'colour_id', 'colour_name', 'gsm', 'finish_dia', 'dia_unit', 'width_type', 'qty', 'uom', 'required_on', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'gsm' => 'float', 'finish_dia' => 'float', 'required_on' => 'date'];
    }
}
