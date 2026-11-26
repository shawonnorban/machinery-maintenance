<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A packing instruction line of a style's order (Item/Combo/Model → Packing Instruction, AnnotechERP 06/05). */
class StylePacking extends BaseModel
{
    use BelongsToTenant;

    public const PACK_TYPES = ['SINGLE', 'MULTI_UNIFORM', 'MULTI_RANDOM'];

    protected $table = 'merch_style_packing';

    protected $fillable = ['company_id', 'style_id', 'sku_ref', 'pack_type', 'pcs_per_pack', 'order_id', 'item_id', 'all_colours', 'colour_id', 'sort_order'];

    protected function casts(): array
    {
        return ['pcs_per_pack' => 'integer', 'all_colours' => 'boolean', 'sort_order' => 'integer'];
    }
}
