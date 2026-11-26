<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A style's yarn / fabric specification for one production category (Style-Wise Yarn Spec Confirmation, AnnotechERP 06/11). */
class YarnSpec extends BaseModel
{
    use BelongsToTenant;

    /** Brickland's production categories, in its order. */
    public const CATEGORIES = ['BULK', 'DEVELOP', 'SHORT_BULK', 'PROJECTION'];

    public const STATUSES = ['PENDING', 'CONFIRMED'];

    protected $table = 'merch_yarn_specs';

    protected $fillable = [
        'company_id', 'style_id', 'prod_category', 'fabric_type_id', 'gmt_item', 'part_ids', 'all_colours', 'gmt_colour_ids', 'fab_colour_ids',
        'status', 'confirmed_at', 'confirmed_by', 'created_by',
    ];

    protected function casts(): array
    {
        return ['part_ids' => 'array', 'gmt_colour_ids' => 'array', 'fab_colour_ids' => 'array', 'all_colours' => 'boolean', 'confirmed_at' => 'datetime'];
    }
}
