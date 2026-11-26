<?php

declare(strict_types=1);

namespace App\Modules\Knitting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A twisting job placed on a party: single yarn out, twisted yarn back.
 */
class KnitTwistProgram extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'APPROVED', 'RUNNING', 'CLOSED', 'CANCELLED'];

    protected $table = 'knit_twist_programs';

    protected $fillable = ['company_id', 'program_no', 'program_date', 'target_date', 'supplier_id', 'store_id', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['program_date' => 'date', 'target_date' => 'date'];
    }
}
