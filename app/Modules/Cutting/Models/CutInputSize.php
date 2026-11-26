<?php

declare(strict_types=1);

namespace App\Modules\Cutting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** One size of a cutting: its marker ratio, the pieces planned (ratio × plies), and what the output found — good and rejected. */
class CutInputSize extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'input_id', 'size', 'ratio', 'planned_qty', 'output_qty', 'reject_qty', 'sort_order'];

    protected function casts(): array
    {
        return ['ratio' => 'integer', 'planned_qty' => 'integer', 'output_qty' => 'integer', 'reject_qty' => 'integer', 'sort_order' => 'integer'];
    }
}
