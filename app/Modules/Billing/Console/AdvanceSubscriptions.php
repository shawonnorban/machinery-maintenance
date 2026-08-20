<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console;

use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Services\SubscriptionLifecycle;
use App\Modules\Billing\Services\UsageMeter;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Moves subscriptions to wherever the calendar says they should be, and
 * measures what each customer used (ADR-011, ADR-029, SRS 40).
 *
 * Both in one daily pass, deliberately: the limits a contract sets are checked
 * against the usage measured in the same run, so a customer is never told they
 * are over a limit on figures from last week.
 *
 * Reports what it did. A lifecycle job that silently stops running means
 * nobody is ever moved to read-only, which looks exactly like every customer
 * paying on time (ADR-061).
 */
class AdvanceSubscriptions extends Command
{
    protected $signature = 'billing:advance {--dry-run : Report what would change without changing it}';

    protected $description = 'Advance subscription lifecycle states and record usage metrics';

    public function handle(
        TenantContext $context,
        SubscriptionLifecycle $lifecycle,
        UsageMeter $meter,
    ): int {
        $companies = Company::withoutGlobalScope(TenantScope::class)->get();

        $moved = 0;
        $measured = 0;
        $exceeded = 0;

        foreach ($companies as $company) {
            $context->forget();
            $context->set($company->id);

            foreach ($meter->measure($company->id) as $metric) {
                $measured++;

                if ($metric->exceeded) {
                    $exceeded++;

                    $this->line(sprintf(
                        '  %s: %s is %s against a limit of %s',
                        $company->code,
                        $metric->metric,
                        rtrim(rtrim((string) $metric->value, '0'), '.'),
                        rtrim(rtrim((string) $metric->limit_value, '0'), '.'),
                    ));
                }
            }

            $contracts = SubscriptionContract::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->get();

            foreach ($contracts as $contract) {
                $before = $contract->status;

                if ($this->option('dry-run')) {
                    continue;
                }

                $after = $lifecycle->advance($contract);

                if ($after !== $before) {
                    $moved++;

                    $this->line("  {$company->code}: {$contract->contract_number} {$before} → {$after}");
                }
            }
        }

        $context->forget();

        $this->info(sprintf(
            'Measured %d usage figures (%d over limit) and moved %d subscriptions across %d companies.',
            $measured,
            $exceeded,
            $moved,
            $companies->count(),
        ));

        return self::SUCCESS;
    }
}
