<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Console;

use App\Modules\Maintenance\Models\MaintenanceSchedule;
use App\Modules\Maintenance\Services\ScheduleGenerator;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * The nightly scheduler tick (ADR-011).
 *
 * A missed run means maintenance quietly stops being generated and nobody
 * notices until an audit, which is why ADR-061 requires an alert on a missed
 * scheduler heartbeat. The command reports what it did so that alert has
 * something to check.
 */
class GenerateMaintenanceSchedules extends Command
{
    protected $signature = 'maintenance:generate-schedules
                            {--company= : Restrict to one company id}';

    protected $description = 'Generate upcoming maintenance schedules and refresh due and overdue states';

    public function handle(ScheduleGenerator $generator, TenantContext $context): int
    {
        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))
            ->where('status', 'ACTIVE')
            ->get();

        $totals = ['plans' => 0, 'created' => 0, 'skipped' => 0, 'due' => 0, 'overdue' => 0];

        foreach ($companies as $company) {
            // Each tenant is processed inside its own context, so every query
            // in the generator stays scoped (ADR-005).
            $context->forget();
            $context->set($company->id);

            $result = $generator->generateAll();
            $states = $this->refreshStates();

            $totals['plans'] += $result['plans'];
            $totals['created'] += $result['created'];
            $totals['skipped'] += $result['skipped'];
            $totals['due'] += $states['due'];
            $totals['overdue'] += $states['overdue'];

            $this->line(sprintf(
                '%-30s plans:%-4d created:%-4d due:%-4d overdue:%-4d',
                $company->code,
                $result['plans'],
                $result['created'],
                $states['due'],
                $states['overdue'],
            ));
        }

        $context->forget();

        $this->info(sprintf(
            'Done. %d companies, %d plans, %d created, %d newly due, %d overdue.',
            $companies->count(),
            $totals['plans'],
            $totals['created'],
            $totals['due'],
            $totals['overdue'],
        ));

        return self::SUCCESS;
    }

    /**
     * Moves occurrences into DUE and OVERDUE as time passes.
     *
     * Overdue is measured against the grace period, not the due date: a plan
     * with a two-day grace is not late on day one, and reporting it as late
     * would make PM compliance meaningless (SRS 31.1).
     *
     * @return array{due: int, overdue: int}
     */
    private function refreshStates(): array
    {
        $due = MaintenanceSchedule::query()
            ->where('status', 'PLANNED')
            ->where('due_at', '<=', now())
            ->update(['status' => 'DUE']);

        $overdue = MaintenanceSchedule::query()
            ->whereIn('status', ['PLANNED', 'DUE'])
            ->whereNotNull('grace_until')
            ->where('grace_until', '<', now())
            ->update(['status' => 'OVERDUE']);

        return ['due' => $due, 'overdue' => $overdue];
    }
}
