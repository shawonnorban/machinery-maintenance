<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Jobs;

use App\Modules\Reporting\Models\ImportJob;
use App\Modules\Reporting\Services\ImportProcessor;
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
 * Runs a large import away from the request (ADR-032).
 *
 * Never retried. An import that half-succeeded and then died must not be
 * replayed from the top: the rows it wrote are already there, and a second pass
 * would update them all again with no way to tell which write was which.
 */
class RunImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        private readonly string $importJobId,
        private readonly string $companyId,
        private readonly string $locale,
        private readonly string $phase,
    ) {}

    public function handle(TenantContext $context, ImportProcessor $processor): void
    {
        $context->forget();
        $context->set($this->companyId);

        App::setLocale($this->locale);

        $job = ImportJob::find($this->importJobId);

        if ($job === null) {
            return;
        }

        $this->phase === 'VALIDATE'
            ? $processor->validate($job)
            : $processor->import($job);
    }

    public function failed(Throwable $e): void
    {
        ImportJob::withoutGlobalScope(TenantScope::class)
            ->where('id', $this->importJobId)
            ->whereIn('status', ['UPLOADED', 'VALIDATING', 'IMPORTING'])
            ->update([
                'status' => 'FAILED',
                'error_message' => Str::limit($e->getMessage(), 500),
                'completed_at' => CarbonImmutable::now(),
            ]);
    }
}
