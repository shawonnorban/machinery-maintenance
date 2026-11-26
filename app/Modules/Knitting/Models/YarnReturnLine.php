<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One receipt line going back, and how much of it.
 */
class YarnReturnLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_yarn_return_lines';

    protected $fillable = ['company_id', 'return_id', 'receipt_line_id', 'qty', 'remarks'];

    protected function casts(): array
    {
        return ['qty' => 'float'];
    }
}
