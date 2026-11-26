<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A CAD marker-consumption record of a job / work style (CAD Consumption Register, AnnotechERP 06/09). */
class CadRecord extends BaseModel
{
    use BelongsToTenant;

    public const CATEGORIES = ['BULK', 'SAMPLE', 'DEVELOPMENT'];

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'IN_PROGRESS', 'SENT', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table = 'merch_cad_records';

    protected $fillable = ['company_id', 'buyer_office_id', 'style_id', 'order_id', 'category', 'job_no', 'record_date', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['record_date' => 'date'];
    }
}
