<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A unit expenditure is charged to, from a sewing line to a whole factory (Cost Centre Master). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccCostCenter extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'acc_cost_centers';

    protected $fillable = ['company_id', 'code', 'name', 'factory_id', 'group_id', 'dept_section', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
