<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Console;

use App\Modules\ItemMaster\Models\ItemLot;
use App\Modules\ItemMaster\Models\ItemStockBalance;
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
 * Chemicals going off (Inventory spec §12).
 *
 * The warning is the point, exactly as it is for warranties: a drum that
 * expired last week is a write-off, while one expiring in thirty days is a
 * decision — use it first, or plan around it. So the sweep only speaks
 * about lots there is still stock of; a lot that has been fully consumed
 * cannot go off, and warning about it trains people to ignore the warning.
 *
 * One message per lot per day at most.
 */
class AlertExpiringLots extends Command
{
    protected $signature = 'itemmaster:expiry-alerts {--days= : Override the notice period}';

    protected $description = 'Warn the store about lots approaching or past their expiry date';

    public function handle(
        TenantContext $context,
        NotificationDispatcher $dispatcher,
        MaintenanceNotifier $roles,
        SettingsResolver $settings,
    ): int {
        $companies = Company::withoutGlobalScope(TenantScope::class)->get();
        $today = CarbonImmutable::today();
        $warned = 0;

        foreach ($companies as $company) {
            $context->forget();
            $context->set($company->id);

            $notice = (int) ($this->option('days') ?? $settings->get('inventory.lot_expiry_notice_days'));

            if ($notice <= 0) {
                continue;
            }

            $lots = ItemLot::query()
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', $today->addDays($notice))
                ->with('item:id,item_code,name')
                ->get();

            foreach ($lots as $lot) {
                // Only what is still on a shelf somewhere.
                $onHand = ItemStockBalance::where('item_id', $lot->item_id)
                    ->where('lot_no', $lot->lot_no)
                    ->get(['quantity_on_hand', 'bin_id'])
                    ->reduce(fn (string $sum, ItemStockBalance $b): string => bcadd($sum, (string) $b->quantity_on_hand, 4), '0.0000');

                if (bccomp($onHand, '0', 4) !== 1) {
                    continue;
                }

                if ($this->alreadyToldToday($lot->id)) {
                    continue;
                }

                $days = $lot->daysToExpiry($today) ?? 0;

                $dispatcher->sendToMany(
                    recipients: $roles->holdersOf('STORE_MANAGER')
                        ->merge($roles->holdersOf('STOREKEEPER'))
                        ->unique('id'),
                    eventType: 'LOT_EXPIRING',
                    data: [
                        'item' => ($lot->item?->name ?? '').' ('.($lot->item?->item_code ?? '').')',
                        'lot_no' => $lot->lot_no,
                        'quantity' => self::trim($onHand),
                        'expiry_date' => $lot->expiry_date->toDateString(),
                        'days' => (string) $days,
                    ],
                    severity: $days < 0 ? 'CRITICAL' : 'WARNING',
                    entityType: 'item_lot',
                    entityId: $lot->id,
                    actionUrl: config('tenancy.frontend_url').'/item-master/'.$lot->item_id,
                );

                $warned++;
            }
        }

        $context->forget();

        $this->info("Warned about $warned lot(s).");

        return self::SUCCESS;
    }

    private function alreadyToldToday(string $lotId): bool
    {
        return Notification::query()
            ->where('event_type', 'LOT_EXPIRING')
            ->where('entity_type', 'item_lot')
            ->where('entity_id', $lotId)
            ->where('created_at', '>=', CarbonImmutable::now()->subDay())
            ->exists();
    }

    private static function trim(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }
}
