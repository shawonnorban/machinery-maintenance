<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Console;

use App\Modules\Analytics\Services\KpiSnapshotter;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Shared\Scopes\TenantScope;
use App\Shared\Support\TenantTimezone;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Writes the daily KPI snapshots dashboards read (ADR-058).
 *
 * Runs hourly rather than nightly. Today's row is a moving figure, and a
 * dashboard still showing this morning's availability at six in the evening is
 * wrong in the way people notice fastest — they stop trusting the whole screen,
 * not just that tile.
 *
 * Reports what it wrote. A scheduled task that quietly stops looks exactly like
 * a quiet week (ADR-061).
 */
class SnapshotKpis extends Command
{
    protected $signature = 'kpi:snapshot {--days=2 : How many days back to recompute}';

    protected $description = 'Precompute daily KPI snapshots for every company and factory';

    public function handle(
        KpiSnapshotter $snapshotter,
        TenantContext $context,
        TenantTimezone $timezone,
    ): int {
        $days = max(1, (int) $this->option('days'));

        // Two days by default, not one: a breakdown closed after midnight
        // changes yesterday's downtime, and a snapshot written before the
        // closure would keep the stale figure until the definition changed.
        $companies = Company::withoutGlobalScope(TenantScope::class)->get();

        $written = 0;

        foreach ($companies as $company) {
            $context->forget();
            $timezone->forget();

            $factories = Factory::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $company->id)
                ->pluck('id');

            $context->set($company->id, $factories->all());

            // Company-wide first, then each factory. A factory row is not
            // derivable from the company one, and vice versa: the two scopes
            // have different asset populations, not different filters over the
            // same numbers.
            $written += $snapshotter->backfillDays($days);

            foreach ($factories as $factoryId) {
                $written += $snapshotter->backfillDays($days, ['factory_id' => $factoryId]);
            }
        }

        $context->forget();
        $timezone->forget();

        $this->info(sprintf(
            'Wrote %d KPI snapshots across %d companies (%d days each).',
            $written,
            $companies->count(),
            $days,
        ));

        return self::SUCCESS;
    }
}
