<?php

declare(strict_types=1);

namespace App\Modules\Settings\Console;

use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\Settings\Services\RetentionPurger;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Settings → Data Retention & Purge: the daily sweep (AnnotechERP 03/04). Each
 * company by its own policies, one at a time, so one company's period never
 * touches another's rows.
 */
class PurgeRetention extends Command
{
    protected $signature = 'settings:purge-retention {--company= : Only this company id}';

    protected $description = "Remove each company's data that is past its retention period";

    public function handle(RetentionPurger $purger, TenantContext $context, AuditRecorder $audit): int
    {
        $companies = Company::query()->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))->pluck('id');

        foreach ($companies as $companyId) {
            $context->forget();
            $context->set($companyId);
            $done = $purger->purge($companyId);
            if (array_sum($done) > 0) {
                $audit->record('RETENTION_PURGED', null, null, $done, companyId: $companyId, label: 'Data Retention & Purge');
            }
            $this->line(sprintf('%s: %d removed', $companyId, array_sum($done)));
        }
        $context->forget();

        return self::SUCCESS;
    }
}
