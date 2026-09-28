<?php

declare(strict_types=1);

namespace App\Modules\ItemMaster\Services;

use App\Modules\Identity\Models\User;
use App\Modules\ItemMaster\Models\FabricInspection;
use App\Modules\ItemMaster\Models\Item;
use App\Modules\ItemMaster\Models\ItemDepartment;
use App\Modules\Notification\Services\MaintenanceNotifier;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Requisition\Models\FloorRequisition;
use Illuminate\Support\Facades\Log;

/**
 * Notifications raised by the item library and its stock ledger.
 *
 * Same contract as MaintenanceNotifier: never propagates a failure, because
 * the movement being announced is already committed and the store is short
 * of the item either way.
 */
class ItemNotifier
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly MaintenanceNotifier $roles,
    ) {}

    /**
     * An issue took an item to or below its reorder level.
     *
     * Goes to the factory's store team rather than to everyone: a low count
     * is something a storekeeper acts on by raising a requisition, not
     * something a technician can do anything about.
     */
    public function lowStock(Item $item, string $onHand, ?string $factoryId = null): void
    {
        $this->guard(function () use ($item, $onHand, $factoryId): void {
            $this->dispatcher->sendToMany(
                recipients: $this->roles->holdersOf('STORE_MANAGER', $factoryId)
                    ->merge($this->roles->holdersOf('STOREKEEPER', $factoryId))
                    ->unique('id'),
                eventType: 'LOW_STOCK',
                data: [
                    'part' => $item->item_code.' — '.$item->name,
                    'on_hand' => self::trim($onHand),
                    'reorder_level' => self::trim((string) $item->reorder_level),
                ],
                severity: 'WARNING',
                factoryId: $factoryId,
                entityType: 'item',
                entityId: $item->id,
                actionUrl: config('tenancy.frontend_url').'/item-master/'.$item->id,
            );
        });
    }

    /**
     * A floor has asked the store for items. The store team of that factory
     * is told; nobody else needs to be.
     */
    public function floorRequisitionSubmitted(FloorRequisition $requisition): void
    {
        $this->guard(function () use ($requisition): void {
            $department = ItemDepartment::find($requisition->target_item_department_id);

            $this->dispatcher->sendToMany(
                recipients: $this->roles->holdersOf('STORE_MANAGER', $requisition->factory_id)
                    ->merge($this->roles->holdersOf('STOREKEEPER', $requisition->factory_id))
                    ->unique('id'),
                eventType: 'FLOOR_REQUISITION_SUBMITTED',
                data: [
                    'number' => $requisition->fr_no,
                    'department' => $department?->name ?? '',
                    'lines' => (string) $requisition->lines()->count(),
                ],
                severity: $requisition->priority === 'URGENT' ? 'CRITICAL' : 'INFO',
                factoryId: $requisition->factory_id,
                entityType: 'floor_requisition',
                entityId: $requisition->id,
                actionUrl: config('tenancy.frontend_url').'/requisitions/'.$requisition->id,
            );
        });
    }

    /**
     * The store has issued against a requisition. Told to whoever raised it:
     * the floor needs to know what is coming and what is not, and a line
     * left short is the half of the message that matters.
     */
    public function floorRequisitionIssued(FloorRequisition $requisition, int $issuedLines, int $shortLines): void
    {
        $this->guard(function () use ($requisition, $issuedLines, $shortLines): void {
            $requester = User::find($requisition->requested_by);

            if ($requester === null) {
                return;
            }

            $this->dispatcher->send(
                recipient: $requester,
                eventType: 'FLOOR_REQUISITION_ISSUED',
                data: [
                    'number' => $requisition->fr_no,
                    'issued' => (string) $issuedLines,
                    'short' => (string) $shortLines,
                ],
                severity: $shortLines > 0 ? 'WARNING' : 'INFO',
                factoryId: $requisition->factory_id,
                entityType: 'floor_requisition',
                entityId: $requisition->id,
                actionUrl: config('tenancy.frontend_url').'/requisitions/'.$requisition->id,
            );
        });
    }

    /**
     * A requisition could not be filled and an automatic purchase
     * requisition is waiting in draft. The store manager is the one who
     * submits it, so they are the one told.
     */
    public function floorRequisitionShortage(FloorRequisition $requisition, int $shortLines, ?string $purchaseRequisitionNo): void
    {
        $this->guard(function () use ($requisition, $shortLines, $purchaseRequisitionNo): void {
            $this->dispatcher->sendToMany(
                recipients: $this->roles->holdersOf('STORE_MANAGER', $requisition->factory_id),
                eventType: 'FLOOR_REQUISITION_SHORTAGE',
                data: [
                    'number' => $requisition->fr_no,
                    'lines' => (string) $shortLines,
                    'pr_no' => $purchaseRequisitionNo ?? '',
                ],
                severity: 'WARNING',
                factoryId: $requisition->factory_id,
                entityType: 'floor_requisition',
                entityId: $requisition->id,
                actionUrl: config('tenancy.frontend_url').'/requisitions/'.$requisition->id,
            );
        });
    }

    /**
     * A lot failed its four-point pass (spec §4.3).
     *
     * Told to the store manager rather than left on the QC sheet,
     * because what happens next is not a QC decision: a wider
     * re-inspection costs a day of somebody's time, and sending the
     * delivery back is a conversation with the supplier.
     */
    public function fabricInspectionFailed(FabricInspection $inspection): void
    {
        $this->guard(function () use ($inspection): void {
            $this->dispatcher->sendToMany(
                recipients: $this->roles->holdersOf('STORE_MANAGER', $inspection->factory_id)
                    ->merge($this->roles->holdersOf('STOREKEEPER', $inspection->factory_id))
                    ->unique('id'),
                eventType: 'FABRIC_INSPECTION_FAILED',
                data: [
                    'number' => $inspection->inspection_no,
                    'percent' => (string) $inspection->penalty_percent,
                    'threshold' => (string) $inspection->acceptance_threshold,
                ],
                severity: 'WARNING',
                factoryId: $inspection->factory_id,
                entityType: 'fabric_inspection',
                entityId: $inspection->id,
                actionUrl: config('tenancy.frontend_url').'/item-master/inspections/'.$inspection->id,
            );
        });
    }

    /**
     * Stock somebody was waiting on has landed (spec §5). Without this the
     * earmark is invisible: the goods sit in the bin and the floor that
     * asked for them three days ago is never told they can be collected.
     */
    public function earmarkStockArrived(Item $item, string $quantity, ?string $factoryId = null, bool $needsAllocation = false): void
    {
        $this->guard(function () use ($item, $quantity, $factoryId, $needsAllocation): void {
            $this->dispatcher->sendToMany(
                recipients: $this->roles->holdersOf('STORE_MANAGER', $factoryId)
                    ->merge($this->roles->holdersOf('STOREKEEPER', $factoryId))
                    ->unique('id'),
                eventType: 'EARMARK_STOCK_ARRIVED',
                data: [
                    'item' => $item->name.' ('.$item->item_code.')',
                    'quantity' => self::trim($quantity),
                    // A roll-tracked item is not allocated automatically —
                    // choosing which roll goes to whom is a person's call.
                    'needs_allocation' => $needsAllocation ? '1' : '',
                ],
                severity: 'INFO',
                factoryId: $factoryId,
                entityType: 'item',
                entityId: $item->id,
                actionUrl: config('tenancy.frontend_url').'/item-master/'.$item->id,
            );
        });
    }

    private static function trim(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('Item notification dispatch failed', ['error' => $e->getMessage()]);
        }
    }
}
