<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One weight into or out of a grey stock line — the grey ledger.
 */
class KnitGreyMove extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_grey_moves';

    protected $fillable = ['company_id', 'lot_id', 'store_id', 'move_date', 'kind', 'doc_no', 'doc_line_id', 'qty', 'rolls', 'value'];

    protected function casts(): array
    {
        return ['move_date' => 'date', 'qty' => 'float', 'rolls' => 'integer', 'value' => 'float'];
    }
}
