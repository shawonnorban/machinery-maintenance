<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Console;

use App\Modules\HrPayroll\Support\MovementBook;
use App\Modules\HrPayroll\Support\SeparationBook;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Puts approved promotions and increments into effect once their effective
 * date has come (AnnotechERP 24/07): the new designation and grade in the
 * posting history, the new Gross A in the salary history; and takes an
 * employee whose approved separation's last day has passed off the roster
 * (24/08).
 */
class ApplyMovements extends Command
{
    protected $signature = 'hr:apply-movements';

    protected $description = 'Apply approved promotions, increments and separations whose date has come';

    public function handle(): int
    {
        foreach (DB::table('hr_employees')->distinct()->pluck('company_id') as $company) {
            $n = (new MovementBook($company))->applyDue();
            $s = (new SeparationBook($company))->applyDue();
            $this->line("{$company}: {$n} applied, {$s} separated");
        }

        return self::SUCCESS;
    }
}
