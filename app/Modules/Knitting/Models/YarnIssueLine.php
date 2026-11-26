<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One requisition line answered, off one lot.
 */
class YarnIssueLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_issue_lines';

    protected $fillable = ['company_id', 'issue_id', 'sort_order', 'req_line_id', 'lot_id', 'qty', 'cones', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'cones' => 'integer'];
    }
}
