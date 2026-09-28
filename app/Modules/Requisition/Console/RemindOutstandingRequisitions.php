<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Console;

use App\Modules\ItemMaster\Actions\ReserveItemStock;
use App\Modules\ItemMaster\Models\ItemReservation;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Services\MaintenanceNotifier;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Requisition\Models\FloorRequisitionIssue;
use App\Modules\Requisition\Models\FloorRequisitionLine;
use App\Modules\Settings\Services\SettingsResolver;
use App\Modules\Tenancy\Models\Company;
use App\Shared\Scopes\TenantScope;
use App\Shared\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * The daily sweep over what a floor requisition left hanging.
 *
 * Three things go quiet if nobody looks at them: a tool lent to the floor
 * and never brought back, a shortage claim on a purchase nobody chased, and
 * a hold whose date has passed. None of them fail loudly — the requisition
 * simply stays open and the stock stays invisible — which is exactly why
 * they need a sweep rather than a screen.
 *
 * Each chase is sent once a day at most, which is what the "already told
 * them today" check is for; a message every morning is a message nobody
 * reads by day three.
 */
class RemindOutstandingRequisitions extends Command
{
    protected $signature = 'requisition:remind-outstanding';

    protected $description = 'Chase borrowed stock that has not come back, claims still waiting on a purchase, and expire overdue holds';

    public function handle(
        TenantContext $context,
        NotificationDispatcher $dispatcher,
        MaintenanceNotifier $roles,
        SettingsResolver $settings,
        ReserveItemStock $reservations,
    ): int {
        $companies = Company::withoutGlobalScope(TenantScope::class)->get();

        $borrowed = 0;
        $waiting = 0;
        $expired = 0;

        foreach ($companies as $company) {
            $context->forget();
            $context->set($company->id);

            $borrowed += $this->chaseBorrowed($dispatcher, $roles, $settings);
            $waiting += $this->chaseWaitingClaims($dispatcher, $roles, $settings);
            $expired += $reservations->expireOverdue();
        }

        $context->forget();

        $this->info("Chased $borrowed borrowed line(s) and $waiting waiting claim(s); expired $expired hold(s).");

        return self::SUCCESS;
    }

    private function chaseBorrowed(
        NotificationDispatcher $dispatcher,
        MaintenanceNotifier $roles,
        SettingsResolver $settings,
    ): int {
        $chased = 0;

        $issues = FloorRequisitionIssue::query()
            ->whereNotNull('issued_at')
            ->whereHas('line', fn ($q) => $q->where('status', 'BORROWED'))
            ->with(['line.requisition:id,fr_no,factory_id'])
            ->get();

        foreach ($issues as $issue) {
            $requisition = $issue->line?->requisition;

            if ($requisition === null || bccomp($issue->outstandingQty(), '0', 4) !== 1) {
                continue;
            }

            $overdueDays = (int) $settings->get('requisition.returnable_overdue_days', factoryId: $requisition->factory_id);
            $daysOut = (int) $issue->issued_at->diffInDays(CarbonImmutable::now());

            if ($overdueDays <= 0 || $daysOut < $overdueDays) {
                continue;
            }

            if ($this->alreadyToldToday('RETURNABLE_OVERDUE', 'floor_requisition_issue', $issue->id)) {
                continue;
            }

            $dispatcher->sendToMany(
                recipients: $roles->holdersOf('STORE_MANAGER', $requisition->factory_id)
                    ->merge($roles->holdersOf('STOREKEEPER', $requisition->factory_id))
                    ->unique('id'),
                eventType: 'RETURNABLE_OVERDUE',
                data: [
                    'item' => $issue->line->item_name,
                    'quantity' => self::trim($issue->outstandingQty()),
                    'number' => $requisition->fr_no,
                    'days' => (string) $daysOut,
                ],
                severity: 'WARNING',
                factoryId: $requisition->factory_id,
                entityType: 'floor_requisition_issue',
                entityId: $issue->id,
                actionUrl: config('tenancy.frontend_url').'/requisitions/'.$requisition->id,
            );

            $chased++;
        }

        return $chased;
    }

    private function chaseWaitingClaims(
        NotificationDispatcher $dispatcher,
        MaintenanceNotifier $roles,
        SettingsResolver $settings,
    ): int {
        $chased = 0;

        $claims = ItemReservation::query()
            ->where('status', 'PENDING_ARRIVAL')
            ->where('reference_type', 'floor_requisition_line')
            ->with(['item:id,item_code,name'])
            ->get();

        foreach ($claims as $claim) {
            $line = FloorRequisitionLine::with('requisition:id,fr_no,factory_id')->find($claim->reference_id);
            $requisition = $line?->requisition;

            if ($requisition === null || $claim->reserved_at === null) {
                continue;
            }

            $reminderDays = (int) $settings->get('requisition.earmark_reminder_days', factoryId: $requisition->factory_id);
            $daysWaiting = (int) $claim->reserved_at->diffInDays(CarbonImmutable::now());

            if ($reminderDays <= 0 || $daysWaiting < $reminderDays) {
                continue;
            }

            if ($this->alreadyToldToday('EARMARK_WAITING', 'item_reservation', $claim->id)) {
                continue;
            }

            $dispatcher->sendToMany(
                recipients: $roles->holdersOf('STORE_MANAGER', $requisition->factory_id),
                eventType: 'EARMARK_WAITING',
                data: [
                    'item' => $claim->item?->name ?? $line->item_name,
                    'quantity' => self::trim($claim->outstanding()),
                    'number' => $requisition->fr_no,
                    'days' => (string) $daysWaiting,
                ],
                severity: 'WARNING',
                factoryId: $requisition->factory_id,
                entityType: 'item_reservation',
                entityId: $claim->id,
                actionUrl: config('tenancy.frontend_url').'/requisitions/'.$requisition->id,
            );

            $chased++;
        }

        return $chased;
    }

    private function alreadyToldToday(string $eventType, string $entityType, string $entityId): bool
    {
        return Notification::query()
            ->where('event_type', $eventType)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('created_at', '>=', CarbonImmutable::now()->subDay())
            ->exists();
    }

    private static function trim(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }
}
