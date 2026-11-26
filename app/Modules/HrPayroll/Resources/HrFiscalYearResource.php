<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Resources;

use App\Modules\Accounting\Resources\FiscalYearResource;

/**
 * Payroll Settings → New Fiscal Year: Finance & Ledger's fiscal year
 * register, which a pay period is filed under, opened from HR so a year can
 * be added without leaving the screen. One register, two doors (AnnotechERP
 * 24/01, 23/01).
 */
class HrFiscalYearResource extends FiscalYearResource
{
    public function key(): string
    {
        return 'hr-fiscal-years';
    }

    public function viewPermission(): string
    {
        return 'hrpayroll.master.view';
    }

    public function managePermission(): string
    {
        return 'hrpayroll.master.manage';
    }
}
