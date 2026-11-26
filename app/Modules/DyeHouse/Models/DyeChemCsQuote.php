<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One supplier's quoted rate for one item of a comparative statement. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemCsQuote extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_cs_quotes';

    protected $fillable = ['company_id', 'cs_line_id', 'supplier_id', 'rate_usd', 'rate_bdt'];

    protected function casts(): array
    {
        return ['rate_usd' => 'float', 'rate_bdt' => 'float'];
    }
}
