<?php

declare(strict_types=1);

namespace App\Modules\Webhook\Providers;

use App\Modules\Asset\Events\AssetStatusChanged;
use App\Modules\Breakdown\Events\BreakdownReported;
use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Webhook\Listeners\ForwardDomainEvents;
use App\Modules\WorkOrder\Events\WorkOrderAssigned;
use App\Modules\WorkOrder\Events\WorkOrderUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(BreakdownReported::class, [ForwardDomainEvents::class, 'onBreakdownReported']);
        Event::listen(AssetStatusChanged::class, [ForwardDomainEvents::class, 'onAssetStatusChanged']);
        Event::listen(WorkOrderAssigned::class, [ForwardDomainEvents::class, 'onWorkOrderAssigned']);
        Event::listen(WorkOrderUpdated::class, [ForwardDomainEvents::class, 'onWorkOrderUpdated']);
        Event::listen(StockChanged::class, [ForwardDomainEvents::class, 'onStockChanged']);
    }
}
