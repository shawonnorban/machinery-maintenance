<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Console;

use App\Modules\Reporting\Models\ReportJob;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes generated report files once they expire (SRS 35).
 *
 * The file goes; the row stays, marked EXPIRED. What was asked for and by whom
 * is the part an audit needs, and it costs a few hundred bytes. What costs
 * something is a copy of a tenant's cost figures sitting on disk in a file
 * nobody remembers exists.
 */
class PruneReportFiles extends Command
{
    protected $signature = 'reports:prune';

    protected $description = 'Delete expired report files and mark their jobs expired';

    public function handle(): int
    {
        // Across tenants deliberately: retention is a platform obligation, not
        // something one company's session happens to trigger.
        $expired = ReportJob::withoutGlobalScope(TenantScope::class)
            ->where('expires_at', '<', CarbonImmutable::now())
            ->whereIn('status', ['COMPLETED', 'QUEUED', 'RUNNING'])
            ->with('file')
            ->get();

        $deleted = 0;

        foreach ($expired as $job) {
            $file = $job->file;

            if ($file !== null && Storage::disk($file->disk)->exists($file->path)) {
                Storage::disk($file->disk)->delete($file->path);
                $deleted++;
            }

            $file?->delete();

            $job->update(['status' => 'EXPIRED', 'file_id' => null]);
        }

        $this->info(sprintf(
            'Expired %d report jobs and deleted %d files.',
            $expired->count(),
            $deleted,
        ));

        return self::SUCCESS;
    }
}
