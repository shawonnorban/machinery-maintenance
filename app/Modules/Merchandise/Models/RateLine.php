<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A standing process or CM rate line the price quotation prices against (Inquiry Rate Chart). Merchandise Desk → Master Data (AnnotechERP 06/01).
 */
class RateLine extends BaseModel
{
    use BelongsToTenant;

    public const GROUPS = ['YARN', 'KNITTING', 'YARN_DYEING', 'AOP', 'DYEING', 'DYEING_FINISHING'];

    protected $table = 'rate_lines';

    protected $fillable = ['company_id', 'rate_group', 'description', 'uom', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
