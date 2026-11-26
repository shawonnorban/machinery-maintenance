<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One indent line answered on an issue.
 */
class KnitNeedleIssueLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_issue_lines';

    protected $fillable = ['company_id', 'issue_id', 'sort_order', 'req_line_id', 'qty', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}
