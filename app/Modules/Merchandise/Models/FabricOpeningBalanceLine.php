<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One fabric batch of an opening balance, its amount in the OB currency and its cost in BDT (Trims & Accessories, AnnotechERP 06/06). */
class FabricOpeningBalanceLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_pfab_ob_lines';

    protected $fillable = [
        'company_id', 'ob_id', 'category', 'description', 'fabric_item', 'colour_id', 'fabric_type_id', 'fabric_group', 'composition_id', 'gsm', 'dia', 'dia_uom',
        'dia_type', 'batch_no', 'rolls', 'roll_nos', 'uom', 'qty', 'rate', 'amount', 'cost_bdt', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['gsm' => 'float', 'dia' => 'float', 'rolls' => 'integer', 'qty' => 'float', 'rate' => 'float', 'amount' => 'float', 'cost_bdt' => 'float', 'sort_order' => 'integer'];
    }
}
