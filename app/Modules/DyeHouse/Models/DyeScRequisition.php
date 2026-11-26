<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Sub-Contract Dye Batch Programme (DF-SC-RQ-, batch DF-SC-B-): grey fabric called off against an out-house programme's shades, and how far the store has issued it. Dye House → S/C Dyeing (AnnotechERP 17/09).
 */
class DyeScRequisition extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_sc_requisitions';

    protected $fillable = ['company_id', 'req_no', 'batch_no', 'req_date', 'programme_id', 'store_id', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['req_date' => 'date'];
    }
}
