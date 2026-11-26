<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One fabric in one shade a party wants knitted, by its serial.
 */
class KnitScReceiptFabric extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_sc_receipt_fabrics';

    protected $fillable = ['company_id', 'receipt_id', 'sl', 'order_id', 'party_ref', 'fabric_type_id', 'colour_id', 'colour_name', 'composition_id', 'gsm', 'qty', 'uom', 'finish_dia', 'dia_unit', 'width_type', 'yd_type', 'feeder', 'mc_dia_id', 'gauge_id', 'rate_type', 'rate', 'start_date', 'end_date', 'remarks'];

    protected function casts(): array
    {
        return ['gsm' => 'float', 'qty' => 'float', 'finish_dia' => 'float', 'rate' => 'float', 'feeder' => 'integer', 'sl' => 'integer', 'start_date' => 'date', 'end_date' => 'date'];
    }
}
