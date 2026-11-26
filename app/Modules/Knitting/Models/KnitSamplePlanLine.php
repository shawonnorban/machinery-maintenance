<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One order or KP on a sample knit plan, with the yarn it needs.
 */
class KnitSamplePlanLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_sample_plan_lines';

    protected $fillable = ['company_id', 'plan_id', 'sort_order', 'buyer_id', 'style_id', 'order_id', 'style_text', 'fabric_type_id', 'colour_id', 'colour_name', 'yarn_details', 'composition_id', 'yarn_count_id', 'colour_group', 'brand', 'qty', 'uom', 'gsm', 'finish_dia', 'machine_dia', 'gauge', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'gsm' => 'float'];
    }
}
