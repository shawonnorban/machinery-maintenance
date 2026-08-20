<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Listeners;

use App\Modules\Asset\Events\AssetStatusChanged;
use App\Modules\Breakdown\Events\BreakdownReported;
use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Webhook\Services\WebhookDispatcher;
use App\Modules\Webhook\Services\WebhookEvents;
use App\Modules\WorkOrder\Events\WorkOrderAssigned;
use App\Modules\WorkOrder\Events\WorkOrderUpdated;

/**
 * Sends the same happenings out to other systems (SRS 43, ADR-035).
 *
 * The websocket events are reused rather than a second set defined. One
 * happening should not have two shapes depending on which transport carried
 * it, and a payload the browser already displays is a payload that has been
 * looked at by somebody.
 *
 * The direction matters: webhooks know about the domain, the domain does not
 * know it is being forwarded. Nothing in the asset or breakdown modules changes
 * because an integration exists.
 */
class ForwardDomainEvents
{
    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function onBreakdownReported(BreakdownReported $event): void
    {
        $this->dispatcher->dispatch(
            $event->breakdown->company_id,
            WebhookEvents::BREAKDOWN_REPORTED,
            $event->broadcastWith(),
        );
    }

    public function onAssetStatusChanged(AssetStatusChanged $event): void
    {
        $this->dispatcher->dispatch(
            $event->asset->company_id,
            WebhookEvents::ASSET_STATUS_CHANGED,
            $event->broadcastWith(),
        );
    }

    public function onWorkOrderAssigned(WorkOrderAssigned $event): void
    {
        $this->dispatcher->dispatch(
            $event->workOrder->company_id,
            WebhookEvents::WORK_ORDER_ASSIGNED,
            $event->broadcastWith(),
        );
    }

    public function onWorkOrderUpdated(WorkOrderUpdated $event): void
    {
        $this->dispatcher->dispatch(
            $event->workOrder->company_id,
            WebhookEvents::WORK_ORDER_UPDATED,
            $event->broadcastWith(),
        );
    }

    public function onStockChanged(StockChanged $event): void
    {
        $this->dispatcher->dispatch(
            $event->part->company_id,
            WebhookEvents::STOCK_CHANGED,
            $event->broadcastWith(),
        );
    }
}
