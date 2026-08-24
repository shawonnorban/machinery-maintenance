<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Events\AssetStatusChanged;
use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Events\BreakdownReported;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Events\StockChanged;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Notification\Events\NotificationCreated;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Events\WorkOrderUpdated;
use App\Shared\Broadcasting\AdvisoryBroadcast;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * What gets broadcast, and where (SRS 29).
 *
 * Two things matter and both fail silently: an event that never fires leaves a
 * screen stale, and an event on the wrong channel shows one company's work to
 * another. The payload matters too — a websocket frame reaches every subscriber
 * on the channel, so anything included is published to all of them.
 */
class BroadcastEventTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
    }

    /**
     * @return list<string>
     */
    private function channelNames(object $event): array
    {
        return array_map(
            fn (PrivateChannel $channel) => (string) $channel,
            $event->broadcastOn(),
        );
    }

    public function test_a_reported_breakdown_is_broadcast_to_its_factory_and_company(): void
    {
        Event::fake([BreakdownReported::class]);

        app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Line stopped',
            'failure_at' => CarbonImmutable::now(),
            'reported_at' => CarbonImmutable::now(),
        ], 'user-a');

        Event::assertDispatched(BreakdownReported::class, function (BreakdownReported $event): bool {
            $channels = $this->channelNames($event);

            // Both: a company-wide dashboard and a factory screen are each
            // listening on their own channel.
            $this->assertContains('private-company.'.$this->delta->id, $channels);
            $this->assertContains('private-factory.'.$this->dhaka->id, $channels);

            return true;
        });
    }

    public function test_a_broadcast_payload_carries_a_row_not_a_record(): void
    {
        Event::fake([BreakdownReported::class]);

        app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => str_repeat('A very long description. ', 40),
            'failure_at' => CarbonImmutable::now(),
            'reported_at' => CarbonImmutable::now(),
        ], 'user-a');

        Event::assertDispatched(BreakdownReported::class, function (BreakdownReported $event): bool {
            $payload = $event->broadcastWith();

            // Enough for a list row and an alert; the full record is fetched
            // over REST by whoever opens it (SRS 29).
            $this->assertSame('breakdown.reported', $event->broadcastAs());
            $this->assertArrayHasKey('number', $payload);
            $this->assertLessThanOrEqual(125, strlen($payload['problem']));
            $this->assertArrayNotHasKey('corrective_action', $payload);
            $this->assertArrayNotHasKey('company_id', $payload);

            return true;
        });
    }

    public function test_a_status_change_is_broadcast_with_where_it_came_from(): void
    {
        Event::fake([AssetStatusChanged::class]);

        app(ChangeAssetStatus::class)->handle($this->asset, 'IDLE', null, 'End of shift', 'MANUAL');

        Event::assertDispatched(AssetStatusChanged::class, function (AssetStatusChanged $event): bool {
            $payload = $event->broadcastWith();

            // A dashboard counting machines by status cannot decrement the one
            // the machine left without being told which it was.
            $this->assertSame('RUNNING', $payload['from_status']);
            $this->assertSame('IDLE', $payload['to_status']);

            return true;
        });
    }

    public function test_a_notification_is_broadcast_only_to_its_recipient(): void
    {
        Event::fake([NotificationCreated::class]);

        $manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        TenantFixture::actingAsTenant($this->delta);

        app(NotificationDispatcher::class)->send(
            $manager,
            'BREAKDOWN_CRITICAL',
            ['asset' => 'SEW-DHK-00412', 'number' => 'BD-1', 'problem' => 'Stopped'],
            'CRITICAL',
        );

        Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($manager): bool {
            // The user channel only. On the company channel it would show one
            // manager's messages to everyone with a tab open.
            $this->assertSame(['private-user.'.$manager->id], $this->channelNames($event));

            return true;
        });
    }

    public function test_a_work_order_transition_is_broadcast_but_a_field_edit_is_not(): void
    {
        $order = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::whereNull('company_id')->value('id'),
            'title' => 'Quarterly service',
        ], 'user-a');

        Event::fake([WorkOrderUpdated::class]);

        app(TransitionWorkOrder::class)->schedule($order, 'user-a');

        Event::assertDispatched(WorkOrderUpdated::class, function (WorkOrderUpdated $event): bool {
            $this->assertSame('DRAFT', $event->broadcastWith()['from_status']);
            $this->assertSame('SCHEDULED', $event->broadcastWith()['status']);

            return true;
        });

        Event::fake([WorkOrderUpdated::class]);

        $order->fresh()->update(['description' => 'A corrected description']);

        // A live list wants to know a job started or finished; it does not want
        // a message every time somebody fixes a typo.
        Event::assertNotDispatched(WorkOrderUpdated::class);
    }

    public function test_stock_movement_is_broadcast_company_wide_with_the_reorder_flag(): void
    {
        $bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $part = InventoryFixture::part($this->delta);

        Event::fake([StockChanged::class]);

        app(ReceiveStock::class)->handle($part, $bin, '10', '2450.0000', 'user-a');

        Event::assertDispatched(StockChanged::class, function (StockChanged $event): bool {
            $payload = $event->broadcastWith();

            // A store serves whichever factories draw from it, so a balance is
            // not a floor-level fact.
            $this->assertSame(['private-company.'.$this->delta->id], $this->channelNames($event));
            $this->assertArrayHasKey('below_reorder_level', $payload);
            $this->assertIsBool($payload['below_reorder_level']);
            $this->assertFalse($payload['below_reorder_level']);

            return true;
        });
    }

    public function test_every_broadcast_event_names_a_channel_scoped_to_one_tenant(): void
    {
        $breakdown = app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Stopped',
            'failure_at' => CarbonImmutable::now(),
            'reported_at' => CarbonImmutable::now(),
        ], 'user-a');

        $events = [
            new BreakdownReported($breakdown),
            new AssetStatusChanged($this->asset, 'RUNNING', 'IDLE'),
        ];

        foreach ($events as $event) {
            foreach ($this->channelNames($event) as $channel) {
                // Every channel is private and carries an id. A bare channel
                // name would be a channel any authenticated client could join.
                $this->assertStringStartsWith('private-', $channel);
                $this->assertMatchesRegularExpression('/^private-(company|factory|user)\.[0-9a-zA-Z]{26}$/', $channel);
            }
        }
    }

    public function test_every_broadcast_is_queued_as_advisory(): void
    {
        $breakdown = app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Stopped',
        ], 'user-a');

        $part = InventoryFixture::part($this->delta);

        $events = [
            new AssetStatusChanged($this->asset, 'RUNNING', 'IDLE'),
            new BreakdownReported($breakdown),
            new StockChanged($part, '10.0000', false),
        ];

        foreach ($events as $event) {
            $name = $event::class;

            // Its own queue, drained after the default one. A websocket server
            // that is down or slow must never delay the notifications,
            // webhooks and exports queued behind it — none of which have
            // anything to do with it.
            $this->assertSame('broadcasts', $event->broadcastQueue(), $name);

            // One attempt. Every one of these announces something already
            // recorded and durable; a retried live update arrives stale, after
            // the screen has been refreshed, and contradicts what is on it.
            $this->assertSame(1, $event->tries, $name);
        }

        // Every broadcast in the product, not merely the three built above:
        // a new event that forgets the trait is a new way for a dead websocket
        // server to block the queue, and nothing else would catch it.
        foreach (glob(app_path('Modules/*/Events/*.php')) as $file) {
            $class = 'App\\Modules\\'
                .basename(dirname($file, 2)).'\\Events\\'.basename($file, '.php');

            if (! is_subclass_of($class, ShouldBroadcast::class)) {
                continue;
            }

            $this->assertContains(
                AdvisoryBroadcast::class,
                class_uses_recursive($class),
                $class.' broadcasts without the advisory queue behaviour.',
            );
        }
    }
}
