<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One lot's balance and value at the end of a struck month. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemClosingLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_closing_lines';

    protected $fillable = ['company_id', 'closing_id', 'lot_id', 'qty', 'value'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'value' => 'float'];
    }
}
