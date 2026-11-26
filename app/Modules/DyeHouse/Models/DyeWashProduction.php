<?php

declare(strict_types=1);

namespace App\Modules\DyeHouse\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * Machine Wash Output: what was dosed on a machine wash requisition, on which machine and for which department; one production per requisition. Dye House → Production Board (AnnotechERP 17/07).
 */
class DyeWashProduction extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'dye_wash_productions';

    protected $fillable = ['company_id', 'requisition_id', 'production_date', 'party_id', 'department_id', 'dye_machine_id', 'requisition_to', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['production_date' => 'date'];
    }
}
