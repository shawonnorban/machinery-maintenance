<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * One programme on an out-house challan; a programme goes out on one challan. Dye House → S/C Dyeing (AnnotechERP 17/09).
 */
class DyeScChallanProgramme extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_sc_challan_programmes';

    protected $fillable = ['company_id', 'challan_id', 'programme_id', 'sort_order'];

    protected function casts(): array
    {
        return [];
    }
}
