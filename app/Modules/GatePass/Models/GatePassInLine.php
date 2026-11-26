<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * How much of one gate pass line a Gate IN brought back. Gate Pass → In (AnnotechERP 11/04).
 */
class GatePassInLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gate_pass_in_lines';

    protected $fillable = ['company_id', 'gate_pass_in_id', 'gate_pass_line_id', 'quantity'];
}
