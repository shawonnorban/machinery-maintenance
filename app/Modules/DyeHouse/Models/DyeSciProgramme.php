<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Sub-Contract Order Register (DFSCI-): a party's dyeing and finishing programme taken in — its buyer and order, and the processes taken on. Dye House → S/C In-House (AnnotechERP 17/10).
 */
class DyeSciProgramme extends BaseModel
{
    use BelongsToTenant;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'PARAMS_UPDATED', 'DESPATCHED', 'COMPLETED', 'CANCELLED'];

    protected $table = 'dye_sci_programmes';

    protected $fillable = ['company_id', 'program_no', 'prog_date', 'supplier_id', 'buyer_id', 'order_id', 'dyeing', 'dyeing_finishing', 'status', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['prog_date' => 'date', 'dyeing' => 'boolean', 'dyeing_finishing' => 'boolean'];
    }
}
