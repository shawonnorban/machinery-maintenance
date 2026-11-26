<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One lot on the lorry back to the spinner.
 */
class YarnReplacementLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_replacement_lines';

    protected $fillable = ['company_id', 'replacement_id', 'sort_order', 'lot_id', 'cartons', 'qty', 'remarks'];

    protected function casts(): array
    {
        return ['cartons' => 'integer', 'qty' => 'float'];
    }
}
