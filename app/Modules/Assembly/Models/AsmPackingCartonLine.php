<?php

declare(strict_types=1);

namespace App\Modules\Assembly\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One size's pieces in a carton. Assembly Floor → Packing Process (AnnotechERP 20/05).
 */
class AsmPackingCartonLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'asm_packing_carton_lines';

    protected $fillable = ['company_id', 'carton_id', 'size', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }
}
