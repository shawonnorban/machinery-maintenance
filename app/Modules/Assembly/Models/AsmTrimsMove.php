<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One stock movement of an item in a floor store, signed: + in, − out. Assembly Floor → Accessories Store (AnnotechERP 20/08).
 */
class AsmTrimsMove extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'asm_trims_moves';

    protected $fillable = ['company_id', 'store_id', 'item_id', 'move_date', 'kind', 'doc_no', 'doc_line_id', 'qty', 'value', 'created_by'];

    protected function casts(): array
    {
        return ['move_date' => 'date', 'qty' => 'decimal:4', 'value' => 'decimal:4'];
    }
}
