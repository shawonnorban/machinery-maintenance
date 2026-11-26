<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Console;

use App\Modules\HrPayroll\Support\AttendanceBook;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Works out every company's daily attendance from the punches in (AnnotechERP
 * 24/04): today and yesterday by default, so a night shift's OUT the next
 * morning closes yesterday's day. Corrected days are left as they are.
 */
class ProcessAttendance extends Command
{
    protected $signature = 'hr:process-attendance {--days=2 : How many days back, today included}';

    protected $description = 'Work out the daily attendance from the attendance terminals punches';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $today = CarbonImmutable::now('Asia/Dhaka');
        $from = $today->subDays($days - 1)->toDateString();
        foreach (DB::table('hr_employees')->distinct()->pluck('company_id') as $company) {
            $n = (new AttendanceBook($company))->process($from, $today->toDateString());
            $this->line("{$company}: {$n} day(s)");
        }

        return self::SUCCESS;
    }
}
