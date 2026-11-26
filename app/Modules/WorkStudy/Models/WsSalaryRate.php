<?php

declare(strict_types=1);

namespace App\Modules\WorkStudy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A month's average operator and ASO salary and overtime rate. Work Study → OT & Salary (AnnotechERP 14/06).
 */
class WsSalaryRate extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'ws_salary_rates';

    protected $fillable = ['company_id', 'period', 'op_salary', 'aso_salary', 'op_ot_rate', 'aso_ot_rate'];

    protected function casts(): array
    {
        return ['op_salary' => 'decimal:2', 'aso_salary' => 'decimal:2', 'op_ot_rate' => 'decimal:2', 'aso_ot_rate' => 'decimal:2'];
    }
}
