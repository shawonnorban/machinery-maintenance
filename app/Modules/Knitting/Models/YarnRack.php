<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Received yarn put away on a rack.
 */
class YarnRack extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_racks';

    protected $fillable = ['company_id', 'lot_id', 'receipt_line_id', 'rack_no', 'qty', 'rack_date', 'created_by'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'rack_date' => 'date'];
    }
}
