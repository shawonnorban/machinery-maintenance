<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One quantity into or out of a yarn lot — the yarn ledger.
 */
class YarnMove extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_moves';

    protected $fillable = ['company_id', 'lot_id', 'store_id', 'move_date', 'kind', 'doc_no', 'doc_line_id', 'qty', 'value'];

    protected function casts(): array
    {
        return ['move_date' => 'date', 'qty' => 'float', 'value' => 'float'];
    }
}
