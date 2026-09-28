<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Jobs;

use App\Modules\Reporting\Models\ReportJob;
use App\Modules\Reporting\Services\ReportRunner;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs a large report away from the request (ADR-032).
 *
 * The job carries the company and locale rather than inheriting them. A queue
 * worker has no session: without an explicit context the tenant scope has
 * nothing to scope to, and without an explicit locale the report comes back in
 * whatever language the worker happens to boot in — which for a Bengali user is
 * a file they cannot read.
 */
class RunReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Reports are expensive and rarely fail transiently. Three attempts at a
     * query that runs out of memory is three times the outage.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        private readonly string $reportJobId,
        private readonly string $companyId,
        private readonly string $locale,
    ) {}

    public function handle(TenantContext $context, ReportRunner $runner): void
    {
        $context->forget();
        $context->set($this->companyId);

        App::setLocale($this->locale);

        $job = ReportJob::find($this->reportJobId);

        if ($job === null) {
            // Cancelled or pruned while it waited. Nothing to do, and nothing
            // worth failing over.
            return;
        }

        $runner->fulfil($job);
    }

    /**
     * A failure must land on the row, not only in the worker log.
     *
     * Without this a report that dies inside the queue stays RUNNING for ever,
     * and the person waiting for it has no way to tell a slow report from a
     * dead one.
     */
    public function failed(Throwable $e): void
    {
        ReportJob::withoutGlobalScope(TenantScope::class)
            ->where('id', $this->reportJobId)
            ->whereIn('status', ['QUEUED', 'RUNNING'])
            ->update([
                'status' => 'FAILED',
                'error_message' => Str::limit($e->getMessage(), 500),
                'completed_at' => CarbonImmutable::now(),
            ]);
    }
}
