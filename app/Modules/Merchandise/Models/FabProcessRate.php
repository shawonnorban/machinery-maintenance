<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A fabric process rate (Costing & Budget → Fabric Process Rate, AnnotechERP 06/09). */
class FabProcessRate extends BaseModel
{
    use BelongsToTenant;

    public const CATEGORIES = ['KNITTING', 'YARN_DYEING', 'AOP', 'DYEING', 'DYEING_FINISHING'];

    public const TEMPLATES = ['COTTON_100', 'LYCRA_BLEND', 'CVC_BLEND'];

    protected $table = 'merch_fab_process_rates';

    protected $fillable = [
        'company_id', 'rate_no', 'category', 'fiber_template', 'count_range', 'fabric_type_id', 'colour_group_id', 'is_slub',
        'description', 'rate', 'currency', 'per', 'prod_rate', 'process_loss', 'active',
    ];

    protected function casts(): array
    {
        return ['rate_no' => 'integer', 'is_slub' => 'boolean', 'active' => 'boolean', 'rate' => 'float', 'prod_rate' => 'float', 'process_loss' => 'float'];
    }
}
