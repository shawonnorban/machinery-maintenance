<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Models\EscalationRule;
use App\Modules\Notification\Models\Notification;
use App\Modules\Notification\Services\EscalationEvaluator;
use App\Modules\Notification\Services\NotificationDispatcher;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Escalation (SRS 28).
 *
 * The rule this file exists to pin down: delay is measured from the original
 * event, never from the previous escalation. Chaining the delays lets a stalled
 * chain drift — two levels each described as "thirty minutes later" become
 * ninety when the first escalation itself runs late, and the factory manager
 * hears about a stopped line an hour after they should have.
 */
class EscalationTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private User $technicianUser;

    private User $manager;

    private User $factoryManager;

    private NotificationDispatcher $dispatcher;

    private EscalationEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->technicianUser = TenantFixture::user($this->delta, 'TECHNICIAN', 'tech@delta.test');
        $this->manager = TenantFixture::user($this->delta, 'MAINTENANCE_MANAGER', 'mm@delta.test');
        $this->factoryManager = TenantFixture::user($this->delta, 'FACTORY_MANAGER', 'fm@delta.test');

        $this->dispatcher = app(NotificationDispatcher::class);
        $this->evaluator = app(EscalationEvaluator::class);
    }

    /**
     * The SRS 28 example: technician, then maintenance manager after thirty
     * minutes, then factory manager after sixty.
     */
    private function rules(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        foreach ([
            [1, 30, 'MAINTENANCE_MANAGER'],
            [2, 60, 'FACTORY_MANAGER'],
        ] as [$level, $delay, $roleCode]) {
            EscalationRule::create([
                'event_type' => 'BREAKDOWN_CRITICAL',
                'delay_minutes' => $delay,
                'escalation_level' => $level,
                'escalation_role_id' => Role::whereNull('company_id')->where('code', $roleCode)->firstOrFail()->id,
                'max_escalations' => 3,
                'stop_on_acknowledge' => true,
                'active' => true,
            ]);
        }
    }

    private function notified(?CarbonImmutable $at = null): Notification
    {
        CarbonImmutable::setTestNow($at ?? CarbonImmutable::parse('2026-08-18 09:00:00'));

        $notification = $this->dispatcher->send(
            $this->technicianUser, 'BREAKDOWN_CRITICAL',
            ['asset' => 'SEW-DHK-00412', 'number' => 'BD-1', 'problem' => 'Line stopped'],
            'CRITICAL',
            $this->dhaka->id,
            'breakdown',
            (string) Str::ulid(),
        );

        CarbonImmutable::setTestNow();

        return $notification;
    }

    public function test_nothing_escalates_before_its_delay(): void
    {
        $this->rules();
        $original = $this->notified();

        $sent = $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 09:20:00'));

        $this->assertSame(0, $sent);
        $this->assertSame(0, Notification::where('escalation_level', '>', 0)->count());
    }

    public function test_the_first_level_fires_after_its_delay(): void
    {
        $this->rules();
        $original = $this->notified();

        $sent = $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 09:35:00'));

        $this->assertSame(1, $sent);

        $escalation = Notification::where('escalation_level', 1)->firstOrFail();

        $this->assertSame($this->manager->id, $escalation->user_id);
        // A new row linked to the original, never a mutation of it: the chain
        // stays auditable and the technician's copy does not change owner
        // (ERD Section 17 rule 3).
        $this->assertSame($original->id, $escalation->source_notification_id);
        $this->assertSame($this->technicianUser->id, $original->fresh()->user_id);
    }

    public function test_the_delay_is_measured_from_the_original_not_the_previous_step(): void
    {
        $this->rules();
        $original = $this->notified();

        // The first escalation runs late — an hour after the event rather than
        // half an hour.
        $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 10:00:00'));
        $this->assertSame(1, Notification::where('escalation_level', 1)->count());

        // Level two is due at 10:00 measured from 09:00. It is due now, not at
        // 11:00. If the delays chained, the factory manager would hear about a
        // stopped line an hour later than the rules promised.
        $this->evaluator->escalate($original->fresh(), CarbonImmutable::parse('2026-08-18 10:00:00'));

        $level2 = Notification::where('escalation_level', 2)->firstOrFail();
        $this->assertSame($this->factoryManager->id, $level2->user_id);
    }

    public function test_acknowledging_stops_the_chain(): void
    {
        $this->rules();
        $original = $this->notified();

        $this->dispatcher->acknowledge($original);

        $sent = $this->evaluator->escalate($original->fresh(), CarbonImmutable::parse('2026-08-18 11:00:00'));

        // Escalating past somebody who has said "I have this" wastes attention
        // and teaches people to ignore the channel.
        $this->assertSame(0, $sent);
    }

    public function test_reading_does_not_stop_the_chain(): void
    {
        $this->rules();
        $original = $this->notified();

        $this->dispatcher->markRead($original);

        $sent = $this->evaluator->escalate($original->fresh(), CarbonImmutable::parse('2026-08-18 09:35:00'));

        // Opening a list is not picking something up.
        $this->assertSame(1, $sent);
    }

    public function test_a_level_never_fires_twice(): void
    {
        $this->rules();
        $original = $this->notified();

        $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 09:35:00'));
        $this->evaluator->escalate($original->fresh(), CarbonImmutable::parse('2026-08-18 09:40:00'));
        $this->evaluator->escalate($original->fresh(), CarbonImmutable::parse('2026-08-18 09:45:00'));

        $this->assertSame(1, Notification::where('escalation_level', 1)->count());
    }

    public function test_a_rule_reaching_nobody_stops_the_chain_rather_than_skipping_ahead(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        EscalationRule::create([
            'event_type' => 'BREAKDOWN_CRITICAL',
            'delay_minutes' => 30,
            'escalation_level' => 1,
            // A role nobody in this company holds.
            'escalation_role_id' => Role::whereNull('company_id')->where('code', 'AUDITOR')->firstOrFail()->id,
            'active' => true,
        ]);

        EscalationRule::create([
            'event_type' => 'BREAKDOWN_CRITICAL',
            'delay_minutes' => 60,
            'escalation_level' => 2,
            'escalation_role_id' => Role::whereNull('company_id')->where('code', 'FACTORY_MANAGER')->firstOrFail()->id,
            'active' => true,
        ]);

        $original = $this->notified();

        $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 11:00:00'));

        // Skipping ahead would quietly send the factory manager something the
        // first level never saw, hiding the misconfiguration.
        $this->assertSame(0, Notification::where('escalation_level', '>', 0)->count());
    }

    public function test_an_escalation_is_at_least_a_warning(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        EscalationRule::create([
            'event_type' => 'WORK_ORDER_ASSIGNED',
            'delay_minutes' => 30,
            'escalation_level' => 1,
            'escalation_role_id' => Role::whereNull('company_id')->where('code', 'MAINTENANCE_MANAGER')->firstOrFail()->id,
            'active' => true,
        ]);

        CarbonImmutable::setTestNow('2026-08-18 09:00:00');
        $original = $this->dispatcher->send($this->technicianUser, 'WORK_ORDER_ASSIGNED', [
            'number' => 'WO-1', 'title' => 'Service', 'asset' => 'A',
        ], 'INFO');
        CarbonImmutable::setTestNow();

        $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 09:35:00'));

        // The thing it is about has now been ignored for a measured period, so
        // it is no longer merely informational.
        $this->assertSame('WARNING', Notification::where('escalation_level', 1)->firstOrFail()->severity);
    }

    public function test_a_rule_for_another_factory_does_not_apply(): void
    {
        $gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');
        TenantFixture::actingAsTenant($this->delta);

        EscalationRule::create([
            'event_type' => 'BREAKDOWN_CRITICAL',
            'factory_id' => $gazipur->id,
            'delay_minutes' => 30,
            'escalation_level' => 1,
            'escalation_role_id' => Role::whereNull('company_id')->where('code', 'MAINTENANCE_MANAGER')->firstOrFail()->id,
            'active' => true,
        ]);

        $original = $this->notified();

        $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 11:00:00'));

        // Narrowing is opt-in and it means what it says.
        $this->assertSame(0, Notification::where('escalation_level', '>', 0)->count());
    }

    public function test_a_company_wide_rule_covers_every_factory(): void
    {
        $this->rules();
        $original = $this->notified();

        // A rule written once keeps working when a new factory opens.
        $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 09:35:00'));

        $this->assertSame(1, Notification::where('escalation_level', 1)->count());
    }

    public function test_the_original_recipient_is_never_told_twice(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        EscalationRule::create([
            'event_type' => 'BREAKDOWN_CRITICAL',
            'delay_minutes' => 30,
            'escalation_level' => 1,
            // Escalating to the person who already has it is not an escalation.
            'escalation_user_id' => $this->technicianUser->id,
            'active' => true,
        ]);

        $original = $this->notified();

        $sent = $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 09:35:00'));

        $this->assertSame(0, $sent);
    }

    public function test_the_scheduled_command_runs_and_reports(): void
    {
        $this->rules();
        $this->notified(CarbonImmutable::now()->subHours(2));

        // A scheduled task that stops working looks exactly like a quiet week,
        // so it says what it did (ADR-061).
        $this->artisan('notifications:escalate')
            ->expectsOutputToContain('Examined')
            ->assertSuccessful();

        // The command walks every tenant and clears the context when it is
        // done, which is right: leaving one company's context set after a
        // multi-tenant job is how a later query silently reads the wrong
        // tenant. The test re-establishes it to look at the result.
        TenantFixture::actingAsTenant($this->delta);

        $this->assertGreaterThan(0, Notification::where('escalation_level', '>', 0)->count());
    }

    public function test_an_inactive_rule_does_nothing(): void
    {
        TenantFixture::actingAsTenant($this->delta);

        EscalationRule::create([
            'event_type' => 'BREAKDOWN_CRITICAL',
            'delay_minutes' => 30,
            'escalation_level' => 1,
            'escalation_role_id' => Role::whereNull('company_id')->where('code', 'MAINTENANCE_MANAGER')->firstOrFail()->id,
            'active' => false,
        ]);

        $original = $this->notified();

        $this->assertSame(0, $this->evaluator->escalate($original, CarbonImmutable::parse('2026-08-18 11:00:00')));
    }
}
