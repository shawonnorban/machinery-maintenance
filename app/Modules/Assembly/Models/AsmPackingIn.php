<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A Quality-passed bundle (or part of it) scanned into a packing batch. Assembly Floor → Packing (AnnotechERP 20/04).
 */
class AsmPackingIn extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'asm_packing_ins';

    protected $fillable = ['company_id', 'batch_id', 'bundle_id', 'quality_out_id', 'in_date', 'size', 'qty', 'created_by'];

    protected function casts(): array
    {
        return ['in_date' => 'date', 'qty' => 'integer'];
    }
}
