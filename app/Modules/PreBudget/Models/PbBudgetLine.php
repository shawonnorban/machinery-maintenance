<?php

declare(strict_types=1);

namespace App\Modules\PreBudget\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What was typed on one budget line (a Knit yarn line or a Woven fabric line,
 * found again by its line_key): Need to Purchase when changed, PI Qty, the
 * suppliers' prices, the chosen price, remarks and what to do with a
 * shortage. Pre-Budget (AnnotechERP 33/34).
 */
class PbBudgetLine extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pb_budget_lines';

    protected $fillable = ['company_id', 'budget_id', 'section', 'line_key', 'need_to_purchase', 'pi_qty', 'price', 'supplier_prices', 'shortage_action', 'remarks', 'no_stock'];

    protected function casts(): array
    {
        return ['need_to_purchase' => 'float', 'pi_qty' => 'float', 'price' => 'float', 'supplier_prices' => 'array', 'no_stock' => 'boolean'];
    }
}
