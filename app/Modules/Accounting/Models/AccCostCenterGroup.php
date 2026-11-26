<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A reporting group cost centres roll into — floor, unit, division (Cost Centre Grouping). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccCostCenterGroup extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'acc_cost_center_groups';

    protected $fillable = ['company_id', 'name', 'display_order', 'remarks', 'active'];

    protected function casts(): array
    {
        return ['display_order' => 'integer', 'active' => 'boolean'];
    }
}
