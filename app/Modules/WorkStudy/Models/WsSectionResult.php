<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a section earned and produced on a day against its target, and what it cost. Work Study → Section Profitability (AnnotechERP 14/08).
 */
class WsSectionResult extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_section_results';

    protected $fillable = ['company_id', 'result_date', 'section_id', 'income_target', 'income', 'prod_target', 'prod_qty', 'oh_cost', 'mat_cost', 'expense', 'comments', 'created_by'];

    protected function casts(): array
    {
        return [
            'result_date' => 'date', 'income_target' => 'float', 'income' => 'float', 'prod_target' => 'float', 'prod_qty' => 'float',
            'oh_cost' => 'float', 'mat_cost' => 'float', 'expense' => 'float',
        ];
    }

    /** Profit/Loss: income less overhead, material and expense — derived, so it never contradicts them. */
    public function profit(): float
    {
        return round((float) $this->income - (float) $this->oh_cost - (float) $this->mat_cost - (float) $this->expense, 2);
    }
}
