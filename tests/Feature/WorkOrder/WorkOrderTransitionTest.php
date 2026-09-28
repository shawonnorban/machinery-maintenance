<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Modules\Asset\Actions\ChangeAssetStatus;
use App\Modules\Asset\Models\Asset;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The work order state machine (SRS 13.1).
 *
 * The transitions themselves are the cheap part. What is tested here is the set
 * of things the state machine refuses, because those refusals are the whole
 * reason a maintenance record survives an audit.
 */
class WorkOrderTransitionTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private TransitionWorkOrder $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->transition = app(TransitionWorkOrder::class);
    }

    private function workOrder(array $overrides = []): WorkOrder
    {
        return app(CreateWorkOrder::class)->handle(array_merge([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Monthly service',
        ], $overrides), 'user-a');
    }

    /** Drives a work order to IN_PROGRESS through the legitimate path. */
    private function inProgress(array $overrides = []): WorkOrder
    {
        $workOrder = $this->workOrder($overrides);
        $workOrder = $this->transition->schedule($workOrder, 'user-a');

        $technician = WorkOrderFixture::technician($this->delta, $this->dhaka);

        app(AssignTechnicians::class)
            ->handle($workOrder, [$technician->id], 'user-a');

        return $this->transition->start($workOrder->fresh(), 'user-a');
    }

    public function test_a_new_work_order_starts_as_a_draft_with_a_number(): void
    {
        $workOrder = $this->workOrder();

        $this->assertSame('DRAFT', $workOrder->status);
        $this->assertNotEmpty($workOrder->work_order_number);
        // The first history row records creation, so the record has no gap
        // before its first transition.
        $this->assertSame(1, $workOrder->statusHistories()->count());
    }

    public function test_a_transition_not_in_the_table_is_refused(): void
    {
        $workOrder = $this->workOrder();

        // DRAFT to COMPLETED would mean work that was never started.
        try {
            $this->transition->complete($workOrder, 'user-a');
            $this->fail('A draft must not be completable.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }

        $this->assertSame('DRAFT', $workOrder->fresh()->status);
    }

    public function test_assigning_without_a_technician_is_refused(): void
    {
        $workOrder = $this->transition->schedule($this->workOrder(), 'user-a');

        // An ASSIGNED work order with nobody on it is a job nobody will do.
        $this->expectException(ValidationException::class);
        $this->transition->assign($workOrder, 'user-a');
    }

    public function test_starting_records_the_actual_start(): void
    {
        $workOrder = $this->inProgress();

        $this->assertSame('IN_PROGRESS', $workOrder->status);
        $this->assertNotNull($workOrder->actual_start);
    }

    public function test_a_shutdown_job_moves_the_machine_under_maintenance_and_back(): void
    {
        $workOrder = $this->inProgress(['requires_shutdown' => true]);

        // The asset record should say the machine is stopped while it is
        // stopped, not after the paperwork is filed.
        $this->assertSame('UNDER_MAINTENANCE', Asset::find($this->asset->id)->status);

        $this->transition->complete($workOrder, 'user-a');

        $this->assertSame('RUNNING', Asset::find($this->asset->id)->status);
    }

    public function test_hold_time_is_excluded_from_repair_time(): void
    {
        CarbonImmutable::setTestNow('2026-08-18 08:00:00');
        $workOrder = $this->inProgress();

        CarbonImmutable::setTestNow('2026-08-18 08:30:00');
        $workOrder = $this->transition->hold($workOrder, 'AWAITING_PARTS', 'user-a', 'Bobbin case on order');

        // Four hours waiting for a part.
        CarbonImmutable::setTestNow('2026-08-18 12:30:00');
        $workOrder = $this->transition->resume($workOrder, 'user-a');

        CarbonImmutable::setTestNow('2026-08-18 13:00:00');
        $workOrder = $this->transition->complete($workOrder, 'user-a');

        // Five hours elapsed, four of them waiting. Repair time is one hour:
        // folding the wait into MTTR would hide a supply problem as slow repair
        // work (ADR-051).
        $this->assertSame(240, $workOrder->hold_minutes);
        $this->assertSame(60, $workOrder->repairMinutes());

        CarbonImmutable::setTestNow();
    }

    public function test_an_unknown_hold_reason_is_refused(): void
    {
        $workOrder = $this->inProgress();

        // Free text here would make "why is work stalling" unanswerable.
        $this->expectException(ValidationException::class);
        $this->transition->hold($workOrder, 'BECAUSE', 'user-a');
    }

    public function test_nobody_can_verify_their_own_work(): void
    {
        $workOrder = $this->inProgress();
        $workOrder->forceFill(['requires_verification' => true])->save();
        $workOrder = $this->transition->complete($workOrder->fresh(), 'user-a');

        try {
            $this->transition->verify($workOrder, 'user-a');
            $this->fail('Self-verification must be refused.');
        } catch (ValidationException $e) {
            // Verification exists to have a second pair of eyes. Without this
            // it is ceremony.
            $this->assertSame(403, $e->status);
        }

        $verified = $this->transition->verify($workOrder->fresh(), 'user-b');
        $this->assertSame('VERIFIED', $verified->status);
        $this->assertSame('user-b', $verified->verified_by);
    }

    public function test_a_work_order_requiring_verification_cannot_be_closed_unverified(): void
    {
        $workOrder = $this->inProgress();
        $workOrder->forceFill(['requires_verification' => true])->save();
        $workOrder = $this->transition->complete($workOrder->fresh(), 'user-a');

        try {
            $this->transition->close($workOrder, 'user-c');
            $this->fail('Closing unverified work must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
        }
    }

    public function test_cancelling_requires_a_reason_and_records_it(): void
    {
        $workOrder = $this->workOrder();

        try {
            $this->transition->cancel($workOrder, 'user-a', '   ');
            $this->fail('An empty cancellation reason must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cancellation_reason', $e->errors());
        }

        $cancelled = $this->transition->cancel($workOrder->fresh(), 'user-a', 'Machine scrapped instead');

        // Cancelled maintenance is a compliance exception, so it is never
        // anonymous.
        $this->assertSame('CANCELLED', $cancelled->status);
        $this->assertSame('Machine scrapped instead', $cancelled->cancellation_reason);
        $this->assertSame('user-a', $cancelled->cancelled_by);
    }

    public function test_a_closed_work_order_is_terminal(): void
    {
        $workOrder = $this->inProgress();
        $workOrder = $this->transition->complete($workOrder, 'user-a');
        $workOrder = $this->transition->close($workOrder, 'user-b');

        $this->assertTrue($workOrder->isTerminal());
        $this->assertSame([], WorkOrder::TRANSITIONS['CLOSED']);

        $this->expectException(ValidationException::class);
        $this->transition->reopen($workOrder, 'user-b', 'Fault returned');
    }

    public function test_reopening_completed_work_is_counted_and_clears_the_sign_off(): void
    {
        $workOrder = $this->inProgress();
        $workOrder = $this->transition->complete($workOrder, 'user-a');

        $reopened = $this->transition->reopen($workOrder, 'user-b', 'Stitch skipping again');

        // A high reopen rate is itself a maintenance-quality signal, so it is
        // counted rather than silently allowed.
        $this->assertSame('IN_PROGRESS', $reopened->status);
        $this->assertSame(1, $reopened->reopened_count);
        $this->assertNull($reopened->completed_by);
        $this->assertNull($reopened->completed_at);
        $this->assertNull($reopened->actual_end);
    }

    public function test_every_transition_is_recorded_with_who_and_why(): void
    {
        $workOrder = $this->inProgress();
        $workOrder = $this->transition->hold($workOrder, 'SHIFT_END', 'user-a');
        $workOrder = $this->transition->resume($workOrder, 'user-b');

        $history = $workOrder->statusHistories()->get();

        $this->assertSame(
            ['IN_PROGRESS', 'ON_HOLD', 'IN_PROGRESS', 'ASSIGNED', 'SCHEDULED', 'DRAFT'],
            $history->pluck('to_status')->all(),
        );

        $hold = $history->firstWhere('to_status', 'ON_HOLD');
        $this->assertSame('SHIFT_END', $hold->reason);
        $this->assertSame('user-a', $hold->changed_by);
    }

    public function test_a_work_order_cannot_be_raised_against_a_scrapped_machine(): void
    {
        app(ChangeAssetStatus::class)
            ->handle($this->asset, 'RETIRED', 'user-a', 'End of life', 'MANUAL');

        $retired = Asset::find($this->asset->id);
        app(ChangeAssetStatus::class)
            ->handle($retired, 'SCRAPPED', 'user-a', 'Sold for parts', 'MANUAL');

        try {
            $this->workOrder();
            $this->fail('A scrapped machine must not accept new work.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }
}
