<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One item counted on a needle store opening sheet.
 */
class KnitNeedleOpening extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_openings';

    protected $fillable = ['company_id', 'store_id', 'ob_date', 'item_id', 'qty', 'rate', 'finalized', 'created_by'];

    protected function casts(): array
    {
        return ['ob_date' => 'date', 'qty' => 'float', 'rate' => 'float', 'finalized' => 'boolean'];
    }
}
