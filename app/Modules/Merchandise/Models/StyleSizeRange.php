<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One set of sizes a style is offered in, picked from Size Master. A line with
 * no sub-style is the style's main line.
 */
class StyleSizeRange extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'merch_style_size_ranges';

    protected $fillable = ['company_id', 'style_id', 'sl_no', 'size_ids', 'sub_style'];

    protected function casts(): array
    {
        return ['size_ids' => 'array', 'sl_no' => 'integer'];
    }
}
