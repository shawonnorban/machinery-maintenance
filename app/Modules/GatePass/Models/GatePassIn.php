<?php

declare(strict_types=1);

namespace App\Modules\GatePass\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One Gate IN against a returnable pass (GPI-2026-000001): what came back,
 * and whether it was the final return. Gate Pass → In (AnnotechERP 11/04).
 */
class GatePassIn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'gate_pass_ins';

    protected $fillable = ['company_id', 'gate_pass_id', 'gpi_no', 'in_at', 'final', 'short_reason', 'remarks', 'received_by'];

    protected function casts(): array
    {
        return ['in_at' => 'datetime', 'final' => 'boolean'];
    }
}
