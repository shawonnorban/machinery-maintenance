<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;

/**
 * A company's accounting year and the calendar year it is registered against (Fiscal Year Setup). Finance & Ledger → Master Data (AnnotechERP 23/01).
 */
class AccFiscalYear extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'acc_fiscal_years';

    protected $fillable = ['company_id', 'name', 'fy_start', 'fy_end', 'cal_start', 'cal_end', 'closed', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['fy_start' => 'date', 'fy_end' => 'date', 'cal_start' => 'date', 'cal_end' => 'date', 'closed' => 'boolean'];
    }
}
