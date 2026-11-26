<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A lab dip request for a buyer to a lab — internal section or nominated house (Lab Dip Programme, AnnotechERP 06/10). */
class LabdipProgram extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'IN_PROGRESS', 'SENT', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table = 'merch_labdip_programs';

    protected $fillable = ['company_id', 'program_no', 'buyer_id', 'buyer_office_id', 'request_date', 'request_section_id', 'request_supplier_id', 'request_by', 'attn_user_id', 'remarks', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['request_date' => 'date'];
    }
}
