<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A garment category's technical variation, with the SMV each order-quantity band attracts. Work Study → Item Specification (AnnotechERP 14/04).
 */
class WsTechVariation extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_tech_variations';

    protected $fillable = ['company_id', 'garment_item_id', 'tech_spec', 'op', 'aso', 'smv', 'band1', 'band2', 'band3', 'band4', 'band5', 'band6', 'sort_order'];

    protected function casts(): array
    {
        return ['op' => 'float', 'aso' => 'float', 'smv' => 'float', 'band1' => 'float', 'band2' => 'float', 'band3' => 'float', 'band4' => 'float', 'band5' => 'float', 'band6' => 'float', 'sort_order' => 'integer'];
    }
}
