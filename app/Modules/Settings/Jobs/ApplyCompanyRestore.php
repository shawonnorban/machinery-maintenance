<?php

declare(strict_types=1);

namespace App\Modules\Settings\Jobs;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Settings\Models\CompanyRestore;
use App\Modules\Settings\Services\CompanyRestorer;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Settings → Backup & Restore: applying a previewed restore, off the request
 * (AnnotechERP 03/04).
 */
class ApplyCompanyRestore implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Never retried: a second attempt would take a second safety backup of a half-known state. */
    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        private readonly string $restoreId,
        private readonly string $companyId,
        private readonly ?string $userId,
    ) {}

    public function handle(TenantContext $context, CompanyRestorer $restorer, AuditRecorder $audit): void
    {
        $context->forget();
        $context->set($this->companyId);

        $restore = CompanyRestore::query()->find($this->restoreId);
        if ($restore === null) {
            return;
        }
        $restore = $restorer->apply($restore, $this->userId);
        $audit->record('RESTORE_'.$restore->status, $restore, companyId: $this->companyId, userId: $this->userId, label: $restore->original_name);
    }

    public function failed(Throwable $e): void
    {
        CompanyRestore::withoutGlobalScope(TenantScope::class)
            ->where('id', $this->restoreId)
            ->whereIn('status', ['QUEUED', 'RUNNING'])
            ->update(['status' => 'FAILED', 'error' => Str::limit($e->getMessage(), 500)]);
    }
}
