<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A knitting / yarn-dyed instruction of a style (Fabrication → Knitting/YD Instruction Entry, AnnotechERP 06/05). */
class StyleKnitInstruction extends BaseModel
{
    use BelongsToTenant;

    public const UNITS = ['KG', 'GM', 'LBS', 'MTR', 'YDS', 'INCH', 'CM', 'PCS', 'CONE', 'GSM', 'COURSE', 'PERCENT'];

    protected $table = 'merch_style_knit_instructions';

    protected $fillable = ['company_id', 'style_id', 'image_title', 'image_ids', 'gmt_colour_ids', 'lines', 'sort_order'];

    protected function casts(): array
    {
        return ['image_ids' => 'array', 'gmt_colour_ids' => 'array', 'lines' => 'array', 'sort_order' => 'integer'];
    }
}
