<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Out-House Delivery Challan (DF-SC-CH-): the programmes despatched to an outside dye house on one challan. Dye House → S/C Dyeing (AnnotechERP 17/09).
 */
class DyeScChallan extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_sc_challans';

    protected $fillable = ['company_id', 'challan_no', 'challan_date', 'party_id', 'supplier_id', 'store_id', 'gate_pass_no', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
