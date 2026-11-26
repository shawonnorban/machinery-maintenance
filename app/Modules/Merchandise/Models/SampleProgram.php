<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** Samples planned against a job / work style (Sample Programme, AnnotechERP 06/10). */
class SampleProgram extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'IN_PROGRESS', 'SENT', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table = 'merch_sample_programs';

    protected $fillable = ['company_id', 'program_no', 'program_date', 'tran_ref', 'buyer_office_id', 'order_id', 'work_style_no', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['program_date' => 'date'];
    }
}
