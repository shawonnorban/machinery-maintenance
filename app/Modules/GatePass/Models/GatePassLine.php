<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One item an item gate pass carries out, with what was declared short at
 * its final return. Gate Pass → Create, In (AnnotechERP 11/03–11/04).
 */
class GatePassLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gate_pass_lines';

    protected $fillable = ['company_id', 'gate_pass_id', 'item_id', 'unit', 'quantity', 'short_qty', 'short_reason', 'remarks', 'sort_order'];
}
