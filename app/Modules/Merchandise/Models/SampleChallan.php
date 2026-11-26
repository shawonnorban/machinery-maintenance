<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/** A delivery challan covering samples couriered to the buyer (Sample Delivery Challan, AnnotechERP 06/10). */
class SampleChallan extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'IN_PROGRESS', 'SENT', 'APPROVED', 'REJECTED', 'CANCELLED'];

    protected $table = 'merch_sample_challans';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'buyer_office_id', 'vehicle_no', 'carrier', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
