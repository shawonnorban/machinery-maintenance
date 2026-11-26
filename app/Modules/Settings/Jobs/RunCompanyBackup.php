<?php

declare(strict_types=1);

namespace App\Modules\Settings\Jobs;

use App\Modules\Settings\Models\CompanyBackup;
use App\Modules\Settings\Services\CompanyBackupWriter;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Settings → Backup & Restore: "Backup now", off the request (AnnotechERP 03/04).
 */
class RunCompanyBackup implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** A backup that ran out of time is not made better by running again at once. */
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        private readonly string $backupId,
        private readonly string $companyId,
    ) {}

    public function handle(TenantContext $context, CompanyBackupWriter $writer): void
    {
        $context->forget();
        $context->set($this->companyId);

        $backup = CompanyBackup::query()->find($this->backupId);
        if ($backup !== null) {
            $writer->write($backup);
        }
    }

    /** A worker that died mid-backup must not leave the row RUNNING for ever. */
    public function failed(Throwable $e): void
    {
        CompanyBackup::withoutGlobalScope(TenantScope::class)
            ->where('id', $this->backupId)
            ->whereIn('status', ['QUEUED', 'RUNNING'])
            ->update(['status' => 'FAILED', 'error' => Str::limit($e->getMessage(), 500), 'finished_at' => CarbonImmutable::now()]);
    }
}
