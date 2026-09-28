<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Console;

use App\Modules\ItemMaster\Models\StockCount;
use App\Modules\ItemMaster\Models\StockCountLine;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Services\MaintenanceNotifier;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Count sheets left open (Inventory spec §12).
 *
 * A sheet's system quantities were frozen the day it was opened. Every day
 * it stays open, the figures on it describe the shelf a little less, and
 * the variance it eventually reports is a little more about the week that
 * passed than about anything the count found. So an open sheet is not
 * merely untidy — it is a measurement going stale, and that is what this
 * says when it chases one.
 *
 * One message per sheet per day at most, the rule the other store sweeps
 * keep.
 */
class ChaseOpenStockCounts extends Command
{
    protected $signature = 'itemmaster:chase-counts {--days= : Override the staleness threshold}';

    protected $description = 'Chase stock count sheets that have been left open';

    public function handle(
        TenantContext $context,
        NotificationDispatcher $dispatcher,
        MaintenanceNotifier $roles,
        SettingsResolver $settings,
    ): int {
        $companies = Company::withoutGlobalScope(TenantScope::class)->get();
        $now = CarbonImmutable::now();
        $chased = 0;

        foreach ($companies as $company) {
            $context->forget();
            $context->set($company->id);

            $days = (int) ($this->option('days') ?? $settings->get('inventory.count_stale_days'));

            if ($days <= 0) {
                continue;
            }

            $recipients = $roles->holdersOf('STORE_MANAGER')
                ->merge($roles->holdersOf('STOREKEEPER'))
                ->unique('id');

            if ($recipients->isEmpty()) {
                continue;
            }

            $counts = StockCount::query()
                // Only what is still being walked or waiting on a decision.
                // An approved sheet is waiting on a posting, which is a
                // different queue and has its own approval notice.
                ->whereIn('status', ['COUNTING', 'UNDER_REVIEW'])
                ->where('created_at', '<=', $now->subDays($days))
                ->with(['store:id,name,code'])
                ->get();

            foreach ($counts as $count) {
                if ($this->alreadyToldToday($count->id)) {
                    continue;
                }

                $uncounted = StockCountLine::where('stock_count_id', $count->id)
                    ->whereNull('counted_qty')
                    ->count();

                $dispatcher->sendToMany(
                    recipients: $recipients,
                    eventType: 'STOCK_COUNT_STALE',
                    data: [
                        'count_no' => $count->count_no,
                        'store' => $count->store?->name ?? '',
                        'days' => (string) (int) $count->created_at->diffInDays($now),
                        'uncounted' => (string) $uncounted,
                    ],
                    severity: $uncounted > 0 ? 'CRITICAL' : 'WARNING',
                    entityType: 'stock_count',
                    entityId: $count->id,
                    actionUrl: config('tenancy.frontend_url').'/item-master/stock-counts/'.$count->id,
                );

                $chased++;
            }
        }

        $context->forget();

        $this->info("Chased $chased count sheet(s).");

        return self::SUCCESS;
    }

    private function alreadyToldToday(string $countId): bool
    {
        return Notification::query()
            ->where('event_type', 'STOCK_COUNT_STALE')
            ->where('entity_type', 'stock_count')
            ->where('entity_id', $countId)
            ->where('created_at', '>=', CarbonImmutable::now()->subDay())
            ->exists();
    }
}
