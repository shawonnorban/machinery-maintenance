<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A dyeing programme for tape, thread and other dyeable trims (Accessories & Trims, AnnotechERP 06/08). */
class TrimDyeingProgram extends BaseModel
{
    use BelongsToTenant;

    public const PROD_TYPES = ['BULK', 'SAMPLE', 'DEVELOPMENT'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'IN_PROGRESS', 'SENT', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table = 'merch_trim_dyeing_programs';

    protected $fillable = ['company_id', 'program_no', 'prod_type', 'buyer_id', 'order_id', 'program_date', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['program_date' => 'date'];
    }
}
