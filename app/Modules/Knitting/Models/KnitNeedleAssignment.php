<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A machine's standard needle fit within a category.
 */
class KnitNeedleAssignment extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_needle_assignments';

    protected $fillable = ['company_id', 'office_id', 'category_id', 'machine_id', 'item_id', 'qty', 'finalized'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'finalized' => 'boolean'];
    }
}
