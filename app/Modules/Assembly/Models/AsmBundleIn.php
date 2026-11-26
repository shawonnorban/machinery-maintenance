<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A cut bundle card taken into sewing onto a line — once — with the pieces short in the bag and the line's plan as it stood. Assembly Floor → Sewing (AnnotechERP 20/01).
 */
class AsmBundleIn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'asm_bundle_ins';

    protected $fillable = ['company_id', 'bundle_id', 'receive_date', 'section', 'floor_id', 'line_id', 'bundle_qty', 'short_qty', 'qty', 'smv', 'manpower', 'daily_target', 'created_by'];

    protected function casts(): array
    {
        return ['receive_date' => 'date', 'bundle_qty' => 'integer', 'short_qty' => 'integer', 'qty' => 'integer', 'smv' => 'decimal:2', 'manpower' => 'integer', 'daily_target' => 'integer'];
    }
}
