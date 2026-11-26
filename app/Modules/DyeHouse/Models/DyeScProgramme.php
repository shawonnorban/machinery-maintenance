<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Out-House Sub-Contract Programme (DF-SC-OH-): dyeing placed with an outside dye house — the party, store and dates, and its shades. Dye House → S/C Dyeing (AnnotechERP 17/09).
 */
class DyeScProgramme extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'PARAMS_UPDATED', 'DESPATCHED', 'COMPLETED', 'CANCELLED'];

    /** The standings grey may be called off and a challan written against. */
    public const LIVE = ['SUBMITTED', 'PARAMS_UPDATED', 'DESPATCHED'];

    protected $table = 'dye_sc_programmes';

    protected $fillable = ['company_id', 'program_no', 'issue_date', 'exp_delivery_date', 'party_id', 'supplier_id', 'store_id', 'gate_pass_no', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'exp_delivery_date' => 'date'];
    }
}
