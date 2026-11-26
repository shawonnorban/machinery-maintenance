<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A unit's Cost/Pc formula on the BOM worksheet (the company's rule, e.g. DZN =
 * [Rate] / 12 * [B.Cons]), stored with column keys. Acc Budget Column Setup.
 */
class PbUnitRule extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pb_unit_rules';

    protected $fillable = ['company_id', 'uom', 'formula', 'note', 'updated_by'];
}
