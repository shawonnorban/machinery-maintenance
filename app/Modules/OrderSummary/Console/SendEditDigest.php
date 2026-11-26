<?php

declare(strict_types=1);

namespace App\Modules\OrderSummary\Console;

use App\Modules\OrderSummary\Models\OsMailSetting;
use App\Modules\OrderSummary\Support\MailDigest;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Order Summary's edit mail (AnnotechERP 32): every minute, each company
 * whose Mail Schedule has a time now due gets its waiting edits sent.
 */
class SendEditDigest extends Command
{
    protected $signature = 'ordersummary:mail-digest {--company= : one company id}';

    protected $description = 'Send Order Summary edit mails whose scheduled time has come.';

    public function handle(TenantContext $context): int
    {
        $now = CarbonImmutable::now();
        $ids = OsMailSetting::query()->withoutGlobalScopes()->where('enabled', true)
            ->when($this->option('company'), fn ($q, $id) => $q->where('company_id', $id))->pluck('company_id');
        foreach (Company::query()->whereKey($ids)->where('status', 'ACTIVE')->get() as $company) {
            $context->forget();
            $context->set($company->id);
            try {
                $r = MailDigest::run($company->id, $now);
                if ($r['changes'] > 0 || $r['sent'] > 0 || isset($r['skipped'])) {
                    $this->line(sprintf('%-20s sent:%d changes:%d %s', $company->code, $r['sent'], $r['changes'], $r['skipped'] ?? ''));
                }
            } catch (Throwable $e) {
                report($e);
                $this->error("{$company->code}: {$e->getMessage()}");
            }
        }
        $context->forget();

        return self::SUCCESS;
    }
}
