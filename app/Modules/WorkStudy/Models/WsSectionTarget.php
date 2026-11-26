<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * What a section is set to produce a day in a month, the rate it is worth and the income that comes to. Work Study → Section Profitability (AnnotechERP 14/08).
 */
class WsSectionTarget extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_section_targets';

    protected $fillable = ['company_id', 'period', 'section_id', 'sub_section', 'daily_target', 'daily_target_inhouse', 'daily_target_subcon', 'avg_rate', 'work_days', 'target_income', 'oh_calculated'];

    protected function casts(): array
    {
        return [
            'daily_target' => 'float', 'daily_target_inhouse' => 'float', 'daily_target_subcon' => 'float', 'avg_rate' => 'float',
            'work_days' => 'integer', 'target_income' => 'float', 'oh_calculated' => 'float',
        ];
    }
}
