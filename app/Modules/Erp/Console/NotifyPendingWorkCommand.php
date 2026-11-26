<?php

declare(strict_types=1);

namespace App\Modules\Erp\Console;

use App\Modules\DevSample\Support\SampleNotices;
use App\Modules\ExportStatement\Support\EsNotices;
use App\Modules\OrderSummary\Support\OrderNotices;
use App\Modules\PreBudget\Support\BudgetNotices;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The morning's pending-work notifications of the sheet modules (user,
 * 2026-11-21): Order Summary's orders near or past RFI/SD and bookings still
 * pending, Dev Sample's submission deadlines, Pre-Budget's costing, stock
 * yarn use and accessories budget still to do, Export Statement's documents and
 * cash incentive claims coming due — each company in turn.
 */
class NotifyPendingWorkCommand extends Command
{
    protected $signature = 'erp:notify-pending-work {--company= : one company code}';

    protected $description = 'Tell each team the Order Summary, Dev Sample, Pre-Budget and Export Statement work that is due.';

    public function handle(TenantContext $context): int
    {
        $companies = Company::query()->where('status', 'ACTIVE')->when($this->option('company'), fn ($q, $code) => $q->where('code', $code))->get();
        foreach ($companies as $company) {
            $context->forget();
            $context->set($company->id);
            $sent = [];
            foreach (['order_summary' => fn () => OrderNotices::pendingWork(), 'dev_sample' => fn () => SampleNotices::deadlines(), 'pre_budget' => fn () => BudgetNotices::pendingWork(), 'export_statement' => fn () => EsNotices::pendingWork()] as $part => $run) {
                try {
                    $sent[$part] = $run();
                } catch (\Throwable $e) {
                    $sent[$part] = 'failed';
                    Log::warning('Pending-work notifications failed', ['company' => $company->code, 'part' => $part, 'error' => $e->getMessage()]);
                }
            }
            $this->line(sprintf('%-12s order summary:%s dev sample:%s pre-budget:%s export statement:%s', $company->code, $sent['order_summary'], $sent['dev_sample'], $sent['pre_budget'], $sent['export_statement']));
        }
        $context->forget();

        return self::SUCCESS;
    }
}
