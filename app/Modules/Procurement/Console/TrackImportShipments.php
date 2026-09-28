<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Console;

use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Services\MaintenanceNotifier;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Procurement\Models\LetterOfCredit;
use App\Modules\Procurement\Models\Shipment;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * What is landing, what is late, and what the bank is about to close
 * (Procurement spec §10).
 *
 * Three sweeps in one command, because they are the same morning question
 * asked from three sides:
 *
 *  - A consignment due within the notice period is a **plan**: the store
 *    has to clear floor space and the merchandiser has to book clearing.
 *  - A consignment past its ETA and still not here is a **problem**, and
 *    it is worth saying so every day until it lands, because the fabric
 *    behind it is holding up a cutting date.
 *  - A letter of credit nearing expiry with nothing shipped against it is
 *    money about to be wasted: an amendment costs a bank charge, but
 *    letting it lapse costs the whole opening.
 *
 * At most one message per document per day, the same rule the other store
 * sweeps keep — a warning repeated hourly is a warning people learn to
 * scroll past.
 */
class TrackImportShipments extends Command
{
    protected $signature = 'procurement:shipment-alerts
        {--days= : Override the ETA notice period}';

    protected $description = 'Report shipments landing soon, shipments past their ETA, and letters of credit about to expire';

    public function handle(
        TenantContext $context,
        NotificationDispatcher $dispatcher,
        MaintenanceNotifier $roles,
        SettingsResolver $settings,
    ): int {
        $companies = Company::withoutGlobalScope(TenantScope::class)->get();
        $today = CarbonImmutable::today();
        $told = 0;

        foreach ($companies as $company) {
            $context->forget();
            $context->set($company->id);

            $recipients = $roles->holdersOf('STORE_MANAGER')
                ->merge($roles->holdersOf('STOREKEEPER'))
                ->unique('id');

            if ($recipients->isEmpty()) {
                continue;
            }

            $notice = (int) ($this->option('days') ?? $settings->get('procurement.shipment_eta_notice_days'));
            $lcNotice = (int) $settings->get('procurement.lc_expiry_notice_days');

            $told += $this->sweepShipments($dispatcher, $recipients, $today, $notice);
            $told += $this->sweepLetters($dispatcher, $recipients, $today, $lcNotice);
        }

        $context->forget();

        $this->info("Reported $told import document(s).");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Modules\Identity\Models\User>  $recipients
     */
    private function sweepShipments(
        NotificationDispatcher $dispatcher,
        $recipients,
        CarbonImmutable $today,
        int $notice,
    ): int {
        if ($notice <= 0) {
            return 0;
        }

        $shipments = Shipment::query()
            ->whereIn('status', ['PLANNED', 'SHIPPED', 'IN_TRANSIT'])
            ->whereNotNull('eta')
            ->where('eta', '<=', $today->addDays($notice))
            ->with(['purchaseOrder:id,po_no', 'vendor:id,name'])
            ->get();

        $told = 0;

        foreach ($shipments as $shipment) {
            if ($this->alreadyToldToday('shipment', $shipment->id)) {
                continue;
            }

            $days = $shipment->daysToEta($today) ?? 0;
            $late = $days < 0;

            $dispatcher->sendToMany(
                recipients: $recipients,
                eventType: $late ? 'SHIPMENT_DELAYED' : 'SHIPMENT_ARRIVING',
                data: [
                    'shipment_no' => $shipment->shipment_no,
                    'po_no' => $shipment->purchaseOrder?->po_no ?? '',
                    'vendor' => $shipment->vendor?->name ?? '',
                    'eta' => $shipment->eta->toDateString(),
                    'days' => (string) abs($days),
                    'document_status' => $shipment->document_status,
                ],
                severity: $late ? 'CRITICAL' : 'WARNING',
                entityType: 'shipment',
                entityId: $shipment->id,
                actionUrl: config('tenancy.frontend_url').'/procurement/shipments',
            );

            $told++;
        }

        return $told;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Modules\Identity\Models\User>  $recipients
     */
    private function sweepLetters(
        NotificationDispatcher $dispatcher,
        $recipients,
        CarbonImmutable $today,
        int $notice,
    ): int {
        if ($notice <= 0) {
            return 0;
        }

        // Only what is still live: a closed LC cannot lapse, and a draft
        // one the bank has not opened has nothing to lose yet.
        $letters = LetterOfCredit::query()
            ->whereIn('status', ['OPENED', 'SHIPPED'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $today->addDays($notice))
            ->with(['purchaseOrder:id,po_no', 'vendor:id,name'])
            ->get();

        $told = 0;

        foreach ($letters as $letter) {
            if ($this->alreadyToldToday('letter_of_credit', $letter->id)) {
                continue;
            }

            $days = $letter->daysToExpiry($today) ?? 0;

            $dispatcher->sendToMany(
                recipients: $recipients,
                eventType: 'LC_EXPIRING',
                data: [
                    'lc_no' => $letter->lc_no,
                    'po_no' => $letter->purchaseOrder?->po_no ?? '',
                    'vendor' => $letter->vendor?->name ?? '',
                    'expiry_date' => $letter->expiry_date->toDateString(),
                    'days' => (string) $days,
                    'amount' => (string) $letter->amount,
                    'currency' => $letter->currency,
                ],
                severity: $days < 0 ? 'CRITICAL' : 'WARNING',
                entityType: 'letter_of_credit',
                entityId: $letter->id,
                actionUrl: config('tenancy.frontend_url').'/procurement/purchase-orders/'.$letter->purchase_order_id,
            );

            $told++;
        }

        return $told;
    }

    private function alreadyToldToday(string $entityType, string $entityId): bool
    {
        return Notification::query()
            ->whereIn('event_type', ['SHIPMENT_ARRIVING', 'SHIPMENT_DELAYED', 'LC_EXPIRING'])
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('created_at', '>=', CarbonImmutable::now()->subDay())
            ->exists();
    }
}
