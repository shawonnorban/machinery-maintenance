<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Heat-setting and other pre-treatment sent out to a subcontractor (Sub-Contract Pre-Treatment Programme); its challan columns fill in when a challan carries it. Dye House → Batch (Bulk) (AnnotechERP 17/03).
 */
class DyePtProgramme extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_pt_programmes';

    protected $fillable = ['company_id', 'program_no', 'supplier_id', 'issue_date', 'exp_delivery_date', 'store_id', 'gate_pass_no', 'challan_id', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'exp_delivery_date' => 'date'];
    }
}
