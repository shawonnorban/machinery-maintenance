<?php

declare(strict_types=1);

namespace App\Modules\Notification\Console;

use App\Modules\Notification\Services\EscalationEvaluator;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Escalates unacknowledged notifications (SRS 28).
 *
 * Reports what it did rather than running silently. A scheduled task that
 * stops working looks exactly like a quiet week, and nobody notices that
 * escalations have not fired until a stopped line goes unreported for a shift
 * (ADR-061).
 */
class EscalateNotifications extends Command
{
    protected $signature = 'notifications:escalate';

    protected $description = 'Escalate notifications nobody has acknowledged';

    public function handle(EscalationEvaluator $evaluator, TenantContext $context): int
    {
        // Every tenant in turn, each inside its own context: escalation rules
        // and recipients are per company, and running unscoped would let one
        // company's rules reach another's people.
        $companies = Company::withoutGlobalScope(TenantScope::class)->get();

        $totals = ['escalated' => 0, 'examined' => 0, 'skipped_acknowledged' => 0];

        foreach ($companies as $company) {
            $context->forget();
            $context->set($company->id);

            $result = $evaluator->run();

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $result[$key];
            }

            if ($result['escalated'] > 0) {
                $this->line(sprintf(
                    '  %s: escalated %d of %d examined',
                    $company->code,
                    $result['escalated'],
                    $result['examined'],
                ));
            }
        }

        $context->forget();

        $this->info(sprintf(
            'Examined %d unacknowledged notifications across %d companies, escalated %d.',
            $totals['examined'],
            $companies->count(),
            $totals['escalated'],
        ));

        return self::SUCCESS;
    }
}
