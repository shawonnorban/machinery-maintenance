<?php

declare(strict_types=1);

namespace App\Modules\Settings\Console;

use App\Modules\Settings\Models\ScheduledJobRun;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Keeps the scheduler's run log to ninety days (Settings → Scheduled Jobs,
 * AnnotechERP 03/04). A five-minute job writes nearly nine thousand rows a
 * month; nobody reads last year's.
 */
class PruneJobRuns extends Command
{
    private const RETENTION_DAYS = 90;

    protected $signature = 'settings:prune-job-runs';

    protected $description = 'Remove scheduler run-log rows older than 90 days';

    public function handle(): int
    {
        $removed = ScheduledJobRun::query()->where('started_at', '<', CarbonImmutable::now()->subDays(self::RETENTION_DAYS))->delete();
        $this->info("Removed {$removed} run-log rows.");

        return self::SUCCESS;
    }
}
