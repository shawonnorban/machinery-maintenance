<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One movement on a lot, signed (+ in, − out), and the paper that made it. Dye House → Chemical Store (AnnotechERP 17/06).
 */
class DyeChemMove extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_chem_moves';

    protected $fillable = ['company_id', 'lot_id', 'move_date', 'kind', 'doc_no', 'doc_line_id', 'qty', 'value', 'created_by'];

    protected function casts(): array
    {
        return ['move_date' => 'date', 'qty' => 'float', 'value' => 'float'];
    }
}
