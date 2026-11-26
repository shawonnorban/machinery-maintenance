<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A head a salary is built from — basic, house rent, medical … (Pay Element). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrPayElement extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_pay_elements';

    protected $fillable = ['company_id', 'code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
