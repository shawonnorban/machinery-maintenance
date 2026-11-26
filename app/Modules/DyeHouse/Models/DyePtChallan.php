<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * The challan fabric leaves on for subcontract pre-treatment, and the programmes it carries (Sub-Contract Pre-Treatment Challan). Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyePtChallan extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_pt_challans';

    protected $fillable = ['company_id', 'challan_no', 'supplier_id', 'challan_date', 'store_id', 'gate_pass_no', 'created_by'];

    protected function casts(): array
    {
        return ['challan_date' => 'date'];
    }
}
