<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One size's marker consumption on a CAD record, for a garment part (AnnotechERP 06/09). */
class CadSize extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_cad_sizes';

    protected $fillable = ['company_id', 'cad_id', 'size', 'garment_part_id', 'consumption', 'uom', 'sort_order'];

    protected function casts(): array
    {
        return ['consumption' => 'float', 'sort_order' => 'integer'];
    }
}
