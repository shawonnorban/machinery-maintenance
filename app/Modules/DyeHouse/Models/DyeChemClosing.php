<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Chemical Month Closing: a struck month and the store's closing balance at its end; the month takes no more movement. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemClosing extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_closings';

    protected $fillable = ['company_id', 'period', 'remarks', 'lots', 'closing_qty', 'closing_value', 'closed_by'];

    protected function casts(): array
    {
        return ['closing_qty' => 'float', 'closing_value' => 'float'];
    }
}
