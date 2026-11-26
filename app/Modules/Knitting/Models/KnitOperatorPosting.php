<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The operator posted to a machine for a schedule, from a moment until another (Machine Operator Assignment). Knit Production → Master Data (AnnotechERP 15/01).
 */
class KnitOperatorPosting extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'knit_operator_postings';

    protected $fillable = ['company_id', 'machine_id', 'user_id', 'shift_id', 'effective_at', 'until_at', 'active'];

    protected function casts(): array
    {
        return ['effective_at' => 'datetime', 'until_at' => 'datetime', 'active' => 'boolean'];
    }
}
