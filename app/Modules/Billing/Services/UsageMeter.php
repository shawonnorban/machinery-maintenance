<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Asset\Models\Asset;
use App\Modules\Billing\Models\SubscriptionContract;
use App\Modules\Billing\Models\UsageMetric;
use App\Modules\Identity\Models\CompanyUser;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Shared\Scopes\TenantScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What each customer is actually using (SRS 40, ADR-028).
 *
 * Measured independently of pricing, on purpose. A renewal argued on "you have
 * about forty machines" against a system that knows it was 412 is a
 * conversation nobody wins, and a contract priced from evidence is easier to
 * defend on both sides.
 *
 * What counts is what the customer can use, not what exists in the database.
 * Scrapped machines and deactivated accounts are not billable: charging for a
 * machine that was sold two years ago is the fastest way to lose the argument
 * and the customer.
 */
class UsageMeter
{
    /**
     * Measure one company for the period containing the given day.
     *
     * @return Collection<int, UsageMetric>
     */
    public function measure(string $companyId, ?CarbonImmutable $on = null): Collection
    {
        $on ??= CarbonImmutable::now();

        $periodStart = $on->startOfMonth();
        $periodEnd = $on->endOfMonth();

        $contract = SubscriptionContract::withoutGlobalScope(TenantScope::class)
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['ARCHIVED', 'CANCELLED'])
            ->orderByDesc('start_date')
            ->first();

        $measurements = [
            'ACTIVE_FACTORIES' => Factory::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('status', 'ACTIVE')
                ->count(),

            // Retired and scrapped machines are excluded for the same reason
            // they are excluded from availability: they are not in service.
            'ACTIVE_ASSETS' => Asset::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->whereNotIn('status', ['RETIRED', 'SCRAPPED', 'LOST', 'DRAFT'])
                ->count(),

            'ACTIVE_USERS' => CompanyUser::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->where('status', 'ACTIVE')
                ->distinct()
                ->count('user_id'),

            // A period figure rather than a headcount: how much work the system
            // carried this month.
            'WORK_ORDERS_CREATED' => WorkOrder::withoutGlobalScope(TenantScope::class)
                ->where('company_id', $companyId)
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count(),
        ];

        $written = collect();

        foreach ($measurements as $metric => $value) {
            $limit = $contract?->limitFor($metric);

            $written->push(UsageMetric::withoutGlobalScope(TenantScope::class)->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'metric' => $metric,
                    'factory_id' => null,
                    'period_start' => $periodStart->toDateString(),
                ],
                [
                    'value' => (string) $value,
                    'limit_value' => $limit === null ? null : (string) $limit,
                    // Null is not zero: a contract naming no limit is
                    // unlimited, and treating its absence as a limit of nothing
                    // would mark every customer as over.
                    'exceeded' => $limit !== null && $value > $limit,
                    'measured_at' => CarbonImmutable::now(),
                    'period_end' => $periodEnd->toDateString(),
                ],
            ));
        }

        return $written;
    }

    /**
     * Whether adding one more of something would breach the contract.
     *
     * Only BLOCK actually stops anything. ALLOW_AND_BILL and WARN_ONLY are
     * commercial answers to going over, not technical ones — a factory that
     * commissions its 413th machine at 2am should not be stopped by a billing
     * rule nobody is awake to relax.
     */
    public function wouldExceed(SubscriptionContract $contract, string $metric, int $current): bool
    {
        if ($contract->overage_policy !== 'BLOCK') {
            return false;
        }

        $limit = $contract->limitFor($metric);

        return $limit !== null && $current >= $limit;
    }

    /**
     * The latest measurement per metric, for the billing screen.
     *
     * @return Collection<string, UsageMetric>
     */
    public function latestFor(string $companyId): Collection
    {
        return UsageMetric::query()
            ->where('company_id', $companyId)
            ->orderByDesc('period_start')
            ->get()
            ->groupBy('metric')
            ->map(fn (Collection $rows) => $rows->first());
    }
}
