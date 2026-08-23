<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Notification\Events\NotificationCreated;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationPreference;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\InventoryFixture;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Notifications (SRS 27, ERD Section 17).
 *
 * The ordering rule is the one that matters: the row is written before
 * anything is sent, so a dropped websocket loses a delivery attempt rather
 * than the message. A technician who never hears about a critical breakdown
 * because a broadcast failed is what this prevents.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private User $manager;

    private User $engineer;

    private User $technicianUser;

    private NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->engineer = TenantFixture::user($this->delta, 'MAINTENANCE_ENGINEER', 'eng@delta.test');
        $this->technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');

        $this->dispatcher = app(NotificationDispatcher::class);
    }

    public function test_a_notification_is_persisted_before_anything_is_sent(): void
    {
        $notification = $this->dispatcher->send(
            $this->manager, 'BREAKDOWN_REPORTED',
            ['asset' => 'SEW-DHK-00412', 'number' => 'BD-1', 'problem' => 'Motor hums'],
            'WARNING',
        );

        // The in-app copy exists the moment the row does; every other channel
        // is an attempt that may or may not land.
        $this->assertNotNull($notification);
        $this->assertSame('SENT', NotificationDelivery::where('channel', 'IN_APP')->firstOrFail()->status);
    }

    public function test_a_failed_broadcast_does_not_lose_the_notification(): void
    {
        // Stands in for a websocket server that is down or unreachable. The
        // notification is already committed by the time this runs, and the
        // recipient can see it in the interface either way.
        Event::listen(NotificationCreated::class, function (): void {
            throw new RuntimeException('Reverb is unreachable');
        });

        $notification = $this->dispatcher->send($this->manager, 'BREAKDOWN_REPORTED', [
            'asset' => 'SEW-DHK-00412', 'number' => 'BD-1', 'problem' => 'Motor hums',
        ]);

        $this->assertNotNull(Notification::find($notification->id));

        // And no row claims the message went out. A delivery record saying SENT
        // when nothing was sent is worse than no record at all.
        $this->assertSame(0, NotificationDelivery::where('channel', 'BROADCAST')->count());
    }

    public function test_a_broadcast_is_recorded_as_handed_over_not_as_received(): void
    {
        $notification = $this->dispatcher->send($this->manager, 'BREAKDOWN_REPORTED', [
            'asset' => 'SEW-DHK-00412', 'number' => 'BD-1', 'problem' => 'Motor hums',
        ]);

        $broadcast = NotificationDelivery::where('channel', 'BROADCAST')->firstOrFail();

        // SENT means the transport took it. A websocket frame that reaches a
        // browser nobody is looking at is not a notification anybody received,
        // and the delivery record must not pretend otherwise.
        $this->assertSame('SENT', $broadcast->status);
        $this->assertNotNull($broadcast->sent_at);
        $this->assertNotNull(Notification::find($notification->id));
    }

    public function test_the_message_is_rendered_in_the_recipient_locale_and_stored(): void
    {
        $this->manager->forceFill(['locale' => 'bn'])->save();

        $notification = $this->dispatcher->send($this->manager->fresh(), 'BREAKDOWN_CRITICAL', [
            'asset' => 'SEW-DHK-00412', 'number' => 'BD-1', 'problem' => 'Motor seized',
        ], 'CRITICAL');

        // Rendered at creation, not at read. A message somebody was sent
        // should still read as the message they were sent, even after they
        // switch the interface language (SRS 48).
        $this->assertSame('bn', $notification->locale);
        $this->assertStringContainsString('জরুরি ব্রেকডাউন', $notification->title);

        app()->setLocale('en');
        $this->assertStringContainsString('জরুরি ব্রেকডাউন', $notification->fresh()->title);
    }

    public function test_an_unknown_event_type_is_refused_loudly(): void
    {
        // A typo would otherwise silently disable the notification it was
        // meant to send.
        $this->expectException(ValidationException::class);
        $this->dispatcher->send($this->manager, 'MACHINE_EXPLODED');
    }

    public function test_reading_is_not_acknowledging(): void
    {
        $notification = $this->dispatcher->send($this->manager, 'BREAKDOWN_REPORTED', [
            'asset' => 'A', 'number' => 'BD-1', 'problem' => 'X',
        ]);

        $this->dispatcher->markRead($notification);

        // Opening a list is not the same as picking something up, and only the
        // second stops an escalation.
        $this->assertTrue($notification->fresh()->isRead());
        $this->assertFalse($notification->fresh()->isAcknowledged());

        $this->dispatcher->acknowledge($notification->fresh());

        $this->assertTrue($notification->fresh()->isAcknowledged());
    }

    public function test_acknowledging_settles_the_whole_chain(): void
    {
        $original = $this->dispatcher->send($this->engineer, 'BREAKDOWN_CRITICAL', [
            'asset' => 'A', 'number' => 'BD-1', 'problem' => 'X',
        ], 'CRITICAL');

        $escalation = $this->dispatcher->send(
            $this->manager, 'BREAKDOWN_CRITICAL',
            ['asset' => 'A', 'number' => 'BD-1', 'problem' => 'X'],
            'CRITICAL', null, null, null, null, 1, $original->id,
        );

        // The manager picking it up means the company admin does not need
        // telling thirty minutes later.
        $this->dispatcher->acknowledge($escalation);

        $this->assertTrue($original->fresh()->isAcknowledged());
        $this->assertTrue($escalation->fresh()->isAcknowledged());
    }

    public function test_in_app_is_always_delivered_whatever_the_preference(): void
    {
        NotificationPreference::create([
            'company_id' => $this->delta->id,
            'user_id' => $this->manager->id,
            'event_type' => 'BREAKDOWN_REPORTED',
            'in_app' => false,
            'email' => false,
        ]);

        $this->dispatcher->send($this->manager, 'BREAKDOWN_REPORTED', [
            'asset' => 'A', 'number' => 'BD-1', 'problem' => 'X',
        ]);

        // The record of what happened is part of the audit trail rather than a
        // preference; the other channels decide how loudly somebody is told.
        $this->assertSame(1, Notification::where('user_id', $this->manager->id)->count());
        $this->assertSame(1, NotificationDelivery::where('channel', 'IN_APP')->count());
    }

    public function test_a_preference_adds_a_pending_delivery_for_other_channels(): void
    {
        NotificationPreference::create([
            'company_id' => $this->delta->id,
            'user_id' => $this->manager->id,
            'event_type' => 'BREAKDOWN_REPORTED',
            'in_app' => true,
            'email' => true,
        ]);

        $this->dispatcher->send($this->manager, 'BREAKDOWN_REPORTED', [
            'asset' => 'A', 'number' => 'BD-1', 'problem' => 'X',
        ]);

        // Recorded as pending rather than sent: email delivery lands with the
        // messaging workstream, and claiming it went out would be a lie in the
        // delivery log.
        $this->assertSame('PENDING', NotificationDelivery::where('channel', 'EMAIL')->firstOrFail()->status);
    }

    public function test_defaults_are_per_event_rather_than_blanket(): void
    {
        // Starting everyone on "email me everything" trains them to filter the
        // lot into a folder.
        $this->assertTrue($this->dispatcher->preferenceFor($this->manager, 'BREAKDOWN_CRITICAL')->email);
        $this->assertFalse($this->dispatcher->preferenceFor($this->manager, 'WORK_ORDER_COMPLETED')->email);
    }

    public function test_reporting_a_breakdown_tells_maintenance(): void
    {
        app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Motor hums but the shaft does not turn',
        ], $this->technicianUser->id);

        $notifications = Notification::whereIn('event_type', ['BREAKDOWN_REPORTED', 'BREAKDOWN_CRITICAL'])->get();

        // The manager and the engineer, both of whom can do something about it.
        $this->assertGreaterThanOrEqual(2, $notifications->count());
        $this->assertTrue($notifications->pluck('user_id')->contains($this->manager->id));
        $this->assertTrue($notifications->pluck('user_id')->contains($this->engineer->id));
    }

    public function test_a_critical_machine_raises_a_different_event_not_just_a_louder_one(): void
    {
        $this->asset->forceFill(['criticality' => 'CRITICAL'])->save();

        app(ReportBreakdown::class)->handle([
            'asset_id' => $this->asset->fresh()->id,
            'problem_description' => 'Line stopped',
        ], $this->technicianUser->id);

        // Escalation rules key off the event, so a line-stopping failure and a
        // spare bench machine can have different chains.
        $this->assertSame(0, Notification::where('event_type', 'BREAKDOWN_REPORTED')->count());
        $this->assertGreaterThan(0, Notification::where('event_type', 'BREAKDOWN_CRITICAL')->count());
        $this->assertSame('CRITICAL', Notification::firstOrFail()->severity);
    }

    public function test_assignment_tells_the_technician_not_their_manager(): void
    {
        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Monthly service',
        ], $this->manager->id);

        $workOrder = app(TransitionWorkOrder::class)->schedule($workOrder, $this->manager->id);

        $technician = WorkOrderFixture::technician(
            $this->delta, $this->dhaka, 'Karim Mia', 'EMP-1001', $this->technicianUser,
        );

        app(AssignTechnicians::class)->handle($workOrder, [$technician->id], $this->manager->id);

        $assigned = Notification::where('event_type', 'WORK_ORDER_ASSIGNED')->get();

        // The person who has to do the work is the person who needs to know.
        $this->assertCount(1, $assigned);
        $this->assertSame($this->technicianUser->id, $assigned->first()->user_id);
    }

    public function test_a_technician_with_no_login_is_skipped_without_failing_the_assignment(): void
    {
        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Monthly service',
        ], $this->manager->id);

        $workOrder = app(TransitionWorkOrder::class)->schedule($workOrder, $this->manager->id);

        // No user account: their supervisor tells them.
        $technician = WorkOrderFixture::technician($this->delta, $this->dhaka);

        $result = app(AssignTechnicians::class)->handle($workOrder, [$technician->id], $this->manager->id);

        $this->assertSame('ASSIGNED', $result->status);
        $this->assertSame(0, Notification::where('event_type', 'WORK_ORDER_ASSIGNED')->count());
    }

    public function test_low_stock_warns_the_store_once_on_the_crossing(): void
    {
        $storeManager = TenantFixture::user($this->delta, 'STORE_MANAGER', 'store@delta.test');
        $bin = InventoryFixture::bin($this->delta, $this->dhaka);
        $part = InventoryFixture::part($this->delta, 'JK-HOOK', 'Rotary hook', ['reorder_level' => '5']);

        $ledger = app(InventoryLedger::class);
        $ledger->post($part, $bin, 'RECEIPT', '10', '250');

        // Still above the level.
        $ledger->post($part, $bin, 'ISSUE', '4');
        $this->assertSame(0, Notification::where('event_type', 'LOW_STOCK')->count());

        // Crosses it.
        $ledger->post($part, $bin, 'ISSUE', '2');
        $this->assertSame(1, Notification::where('event_type', 'LOW_STOCK')->count());
        $this->assertSame($storeManager->id, Notification::where('event_type', 'LOW_STOCK')->firstOrFail()->user_id);

        // Repeating it on each of the next twenty issues is how people learn to
        // ignore the warning.
        $ledger->post($part, $bin, 'ISSUE', '1');
        $this->assertSame(1, Notification::where('event_type', 'LOW_STOCK')->count());
    }

    public function test_a_notification_failure_never_rolls_back_the_thing_it_announces(): void
    {
        // No maintenance manager or engineer exists in this company, so there
        // is nobody to tell. The breakdown must still be recorded: the machine
        // is down either way.
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        $narayanganj = TenantFixture::factory($omega, 'Narayanganj Unit', 'NGJ');
        TenantFixture::actingAsTenant($omega);

        $asset = WorkOrderFixture::runningAsset($omega, $narayanganj, 'SEW-NGJ-00001');

        $breakdown = app(ReportBreakdown::class)->handle([
            'asset_id' => $asset->id,
            'problem_description' => 'Stopped',
        ], null);

        $this->assertNotNull($breakdown->id);
        $this->assertSame('REPORTED', $breakdown->status);
    }

    public function test_notifications_do_not_cross_tenants(): void
    {
        $this->dispatcher->send($this->manager, 'BREAKDOWN_REPORTED', [
            'asset' => 'A', 'number' => 'BD-1', 'problem' => 'X',
        ]);

        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        TenantFixture::actingAsTenant($omega);

        // The global scope refuses another company's rows rather than returning
        // them unscoped.
        $this->assertSame(0, Notification::count());
    }
}
