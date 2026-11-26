<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A budget's cost rate: a fabrication's Knitting / Dyeing Finishing / Yarn Dyeing / AOP (BDT per kg) or a style's Embellishment, CM and Badge (USD per dozen; the badge with its wastage factor, supplier and remarks), keyed by the style name. Knit/Dying Costing, Pre-Budget (AnnotechERP 33/34).
 */
class PbBudgetCost extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pb_budget_costs';

    protected $fillable = ['company_id', 'budget_id', 'kind', 'cost_key', 'knit_rate', 'dye_rate', 'yarn_dye_rate', 'aop_rate', 'emb_per_dz', 'cm_per_dz', 'badge_wastage', 'badge_per_dz', 'badge_supplier', 'badge_remarks'];

    protected function casts(): array
    {
        return ['knit_rate' => 'float', 'dye_rate' => 'float', 'yarn_dye_rate' => 'float', 'aop_rate' => 'float', 'emb_per_dz' => 'float', 'cm_per_dz' => 'float', 'badge_wastage' => 'float', 'badge_per_dz' => 'float'];
    }
}
