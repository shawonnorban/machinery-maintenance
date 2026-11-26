<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A named rule a gazette publishes (Wage/Gazette Rule). HR & Payroll → Master Data (AnnotechERP 24/01).
 */
class HrGazettePolicy extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'hr_gazette_policies';

    protected $fillable = ['company_id', 'gazette_id', 'code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
