<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A fabric a style is cut from (Item/Combo/Model → Fabrication, AnnotechERP 06/05). */
class StyleFabric extends BaseModel
{
    use BelongsToTenant;

    public const CATEGORIES = ['KNIT', 'WOVEN', 'YARN_DYED', 'SWEATER'];

    public const SOURCES = ['MANUFACTURED', 'PURCHASED'];

    public const SPIN_TYPES = ['COMBED', 'CARDED', 'OPEN_END', 'COMPACT'];

    public const WEIGHT_UNITS = ['GSM', 'OZ', 'GM'];

    /** Brickland offers the same list for wash and finish. */
    public const WASHES = ['Normal Wash', 'Enzyme', 'Silicon', 'Oil Wash', 'Strong Wash', 'Acid Wash', 'Tie Dye', 'Batik', 'Pigment Dye', 'Cool Dye', 'Deep Dye', 'Panel Wash', 'Steam Tumble', 'Golfball', 'Dry Tumbling with Balls', 'Ombre Effect', 'Spray Effect'];

    public const COUNT_PARTS = ['top', 'binding', 'back'];

    protected $table = 'merch_style_fabrics';

    protected $fillable = [
        'company_id', 'style_id', 'item_ids', 'garment_part_ids', 'fabric_code', 'fabric_category', 'source', 'fiber_composition_id', 'spin_type', 'is_slub', 'is_melange', 'is_main',
        'fabric_type_id', 'weight_from', 'weight_to', 'weight_unit', 'width', 'supplier', 'remarks', 'counts', 'wash_types', 'wash_na', 'finish_types', 'finish_na', 'other_spec', 'description', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'item_ids' => 'array', 'garment_part_ids' => 'array', 'counts' => 'array', 'wash_types' => 'array', 'finish_types' => 'array',
            'is_slub' => 'boolean', 'is_melange' => 'boolean', 'is_main' => 'boolean', 'wash_na' => 'boolean', 'finish_na' => 'boolean',
            'weight_from' => 'decimal:2', 'weight_to' => 'decimal:2', 'sort_order' => 'integer',
        ];
    }
}
