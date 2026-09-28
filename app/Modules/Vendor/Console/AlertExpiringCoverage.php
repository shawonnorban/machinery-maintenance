<?php

declare(strict_types=1);

namespace App\Modules\Vendor\Console;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PermissionResolver;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Vendor\Models\ServiceContract;
use App\Modules\Vendor\Models\Warranty;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Warns before cover runs out, and marks it expired after (SRS 26, ADR-011).
 *
 * The warning is the point. A warranty that lapsed last month is a fact nobody
 * can act on; one lapsing in thirty days is a decision — renew, extend, or
 * accept the risk knowingly. Alerts go out at intervals rather than daily,
 * because a message every morning for sixty days is a message nobody reads by
 * day three.
 */
class AlertExpiringCoverage extends Command
{
    protected $signature = 'vendor:coverage-alerts {--days= : Override the notice period}';

    protected $description = 'Notify about expiring warranties and service contracts, and expire what has lapsed';

    /**
     * One alert at each threshold, so a person hears about it early enough to
     * negotiate and again when it becomes urgent.
     */
    private const THRESHOLDS = [60, 30, 7];

    public function handle(
        TenantContext $context,
        NotificationDispatcher $dispatcher,
        SettingsResolver $settings,
        PermissionResolver $permissions,
    ): int {
        $companies = Company::withoutGlobalScope(TenantScope::class)->get();

        $alerted = 0;
        $expired = 0;

        foreach ($companies as $company) {
            $context->forget();
            $context->set($company->id);

            $expired += $this->expireLapsed();

            // Split by permission rather than lumped together: expiring cover
            // on a machine is a maintenance question, while a contract ending
            // is a commercial one, and the roles that hold them differ.
            $warrantyRecipients = $this->recipientsFor($company->id, $permissions, 'vendor.warranty.manage');
            $contractRecipients = $this->recipientsFor($company->id, $permissions, 'vendor.contract.manage');

            if ($warrantyRecipients->isEmpty() && $contractRecipients->isEmpty()) {
                // Nobody holds either permission, so there is nobody to tell.
                // Said in the output rather than silently skipped.
                $this->line("  {$company->code}: nobody can act on expiring cover, no alerts sent");

                continue;
            }

            foreach ($this->thresholds() as $days) {
                $alerted += $this->alertWarranties($days, $warrantyRecipients, $dispatcher);
                $alerted += $this->alertContracts($days, $contractRecipients, $dispatcher);
            }
        }

        $context->forget();

        $this->info(sprintf(
            'Expired %d lapsed records and sent %d expiry alerts across %d companies.',
            $expired,
            $alerted,
            $companies->count(),
        ));

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function thresholds(): array
    {
        $override = $this->option('days');

        return $override === null ? self::THRESHOLDS : [(int) $override];
    }

    private function expireLapsed(): int
    {
        $today = CarbonImmutable::now()->toDateString();

        // Status follows the calendar. Anything still reading ACTIVE after its
        // end date would show as cover on a screen that decides whether to pay
        // for a repair.
        $warranties = Warranty::where('status', 'ACTIVE')
            ->where('end_date', '<', $today)
            ->update(['status' => 'EXPIRED']);

        $contracts = ServiceContract::where('status', 'ACTIVE')
            ->where('end_date', '<', $today)
            ->update(['status' => 'EXPIRED']);

        return $warranties + $contracts;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(string $companyId, PermissionResolver $permissions, string $permission)
    {
        return User::whereHas('memberships', fn ($q) => $q->where('company_id', $companyId)
            ->where('status', 'ACTIVE'))
            ->where('status', 'ACTIVE')
            ->get()
            ->filter(fn (User $user) => $permissions->has($user, $companyId, $permission));
    }

    private function alertWarranties(int $days, $recipients, NotificationDispatcher $dispatcher): int
    {
        $sent = 0;

        // Exactly on the threshold, not within it: "expiring within 30 days"
        // run daily would send the same warning thirty times.
        $target = CarbonImmutable::now()->addDays($days)->toDateString();

        $warranties = Warranty::with(['asset', 'vendor'])
            ->where('status', 'ACTIVE')
            ->whereDate('end_date', $target)
            ->get();

        foreach ($warranties as $warranty) {
            foreach ($recipients as $recipient) {
                $dispatcher->send(
                    $recipient,
                    'WARRANTY_EXPIRY',
                    [
                        'asset' => $warranty->asset?->asset_code ?? '—',
                        'vendor' => $warranty->vendor?->name ?? __('vendor.unnamed_vendor'),
                        'days' => $days,
                        'end_date' => $warranty->end_date->format('Y-m-d'),
                    ],
                    $days <= 7 ? 'WARNING' : 'INFO',
                    $warranty->asset?->current_factory_id,
                    'warranty',
                    $warranty->id,
                );

                $sent++;
            }
        }

        return $sent;
    }

    private function alertContracts(int $days, $recipients, NotificationDispatcher $dispatcher): int
    {
        $sent = 0;
        $target = CarbonImmutable::now()->addDays($days)->toDateString();

        $contracts = ServiceContract::with('vendor')
            ->where('status', 'ACTIVE')
            ->whereDate('end_date', $target)
            ->get();

        foreach ($contracts as $contract) {
            foreach ($recipients as $recipient) {
                $dispatcher->send(
                    $recipient,
                    'AMC_EXPIRY',
                    [
                        'number' => $contract->contract_number,
                        'vendor' => $contract->vendor?->name ?? __('vendor.unnamed_vendor'),
                        'days' => $days,
                        'end_date' => $contract->end_date->format('Y-m-d'),
                    ],
                    $days <= 7 ? 'WARNING' : 'INFO',
                    $contract->factory_id,
                    'service_contract',
                    $contract->id,
                );

                $sent++;
            }
        }

        return $sent;
    }
}
