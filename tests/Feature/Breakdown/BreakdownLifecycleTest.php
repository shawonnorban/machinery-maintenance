<?php

declare(strict_types=1);

namespace Tests\Feature\Breakdown;

use App\Modules\Asset\Models\Asset;
use App\Modules\Breakdown\Actions\RaiseBreakdownWorkOrder;
use App\Modules\Breakdown\Actions\ReportBreakdown;
use App\Modules\Breakdown\Actions\TransitionBreakdown;
use App\Modules\Breakdown\Models\Breakdown;
use App\Modules\Breakdown\Models\FailureCode;
use App\Modules\Breakdown\Models\RootCause;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Models\Technician;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * The breakdown lifecycle and its timestamp chain (SRS 15, ERD Section 10).
 *
 * The chain rules matter because breaking them does not throw at read time. It
 * quietly produces a negative response time or an impossible repair duration,
 * and those numbers reach a management report looking exactly as credible as
 * the correct ones.
 */
class BreakdownLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Asset $asset;

    private Technician $technician;

    private ReportBreakdown $report;

    private TransitionBreakdown $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);

        $this->technician = WorkOrderFixture::technician($this->delta, $this->dhaka);

        $this->report = app(ReportBreakdown::class);
        $this->transition = app(TransitionBreakdown::class);
    }

    private function reported(array $overrides = []): Breakdown
    {
        return $this->report->handle(array_merge([
            'asset_id' => $this->asset->id,
            'problem_description' => 'Machine stops mid-seam, motor hums but shaft does not turn',
        ], $overrides), 'user-a');
    }

    public function test_reporting_needs_only_a_machine_and_a_description(): void
    {
        $breakdown = $this->reported();

        // Three fields. Demanding a diagnosis from an operator standing at a
        // stopped machine gets a delayed report or a guessed code.
        $this->assertSame('REPORTED', $breakdown->status);
        $this->assertNotEmpty($breakdown->breakdown_number);
        $this->assertNotNull($breakdown->failure_at);
        $this->assertNotNull($breakdown->reported_at);
        $this->assertNull($breakdown->failure_code_id);
    }

    public function test_reporting_stops_the_machine_and_writes_downtime_immediately(): void
    {
        $breakdown = $this->reported();

        // The asset record should say the machine is down while it is down.
        $this->assertSame('BREAKDOWN', Asset::find($this->asset->id)->status);

        // Written at report time, so an open stoppage is visible as it accrues
        // rather than reading as zero until somebody closes it.
        $this->assertNotNull($breakdown->currentDowntime());
    }

    public function test_priority_defaults_to_the_machines_criticality(): void
    {
        $this->asset->forceFill(['criticality' => 'CRITICAL'])->save();

        $breakdown = $this->reported();

        // A critical machine stopping is a critical breakdown; the reporter
        // should not have to decide that while a line is stopped.
        $this->assertSame('CRITICAL', $breakdown->priority);
    }

    public function test_a_failure_after_the_report_is_refused(): void
    {
        // Almost always a mistyped date. Left alone it produces a negative
        // response time that poisons the average.
        $this->expectException(ValidationException::class);
        $this->reported([
            'failure_at' => '2026-08-18 10:00:00',
            'reported_at' => '2026-08-18 09:00:00',
        ]);
    }

    public function test_a_second_report_on_a_machine_already_down_is_linked_not_counted(): void
    {
        $first = $this->reported();
        $second = $this->reported(['problem_description' => 'Still not running']);

        // Counting it independently would halve MTBF for a machine that broke
        // once (ERD Section 10 rule 4).
        $this->assertSame($first->id, $second->is_recurrence_of_breakdown_id);
        $this->assertNull($first->fresh()->is_recurrence_of_breakdown_id);
        $this->assertCount(1, $first->fresh()->recurrences);
    }

    public function test_the_chain_records_response_and_repair_separately(): void
    {
        CarbonImmutable::setTestNow('2026-08-18 09:00:00');
        $breakdown = $this->reported();

        CarbonImmutable::setTestNow('2026-08-18 09:10:00');
        $breakdown = $this->transition->acknowledge($breakdown, 'user-b');

        CarbonImmutable::setTestNow('2026-08-18 09:15:00');
        $breakdown = $this->transition->assign($breakdown, $this->technician->id, 'user-b');

        CarbonImmutable::setTestNow('2026-08-18 09:30:00');
        $breakdown = $this->transition->recordArrival($breakdown, 'user-c');

        CarbonImmutable::setTestNow('2026-08-18 09:35:00');
        $breakdown = $this->transition->startRepair($breakdown, 'user-c');

        CarbonImmutable::setTestNow('2026-08-18 10:35:00');
        $breakdown = $this->transition->completeRepair($breakdown, 'user-c');

        CarbonImmutable::setTestNow('2026-08-18 10:50:00');
        $breakdown = $this->transition->resumeProduction($breakdown, 'user-c');

        $downtime = $breakdown->currentDowntime();

        // Report to arrival: 30 minutes. Measured from the report, not the
        // failure, because time before anyone said so is a reporting problem.
        $this->assertSame(30, $downtime->response_minutes);
        // Repair start to repair end: 60 minutes.
        $this->assertSame(60, $downtime->repair_minutes);
        // Failure to production resuming: 110 minutes. The 15 minutes between
        // the machine being fixed and the line running again are real lost
        // output and are counted.
        $this->assertSame(110, $downtime->total_downtime_minutes);

        CarbonImmutable::setTestNow();
    }

    public function test_hold_time_is_excluded_from_repair_time(): void
    {
        CarbonImmutable::setTestNow('2026-08-18 09:00:00');
        $breakdown = $this->reported();
        $breakdown = $this->transition->acknowledge($breakdown, 'user-b');

        CarbonImmutable::setTestNow('2026-08-18 09:10:00');
        $breakdown = $this->transition->startRepair($breakdown, 'user-c');

        CarbonImmutable::setTestNow('2026-08-18 09:20:00');
        $breakdown = $this->transition->hold($breakdown, 'AWAITING_PARTS', 'user-c', 'Hook on order');

        CarbonImmutable::setTestNow('2026-08-18 11:20:00');
        $breakdown = $this->transition->resume($breakdown, 'user-c');

        CarbonImmutable::setTestNow('2026-08-18 11:30:00');
        $breakdown = $this->transition->completeRepair($breakdown, 'user-c');

        // 140 minutes elapsed on the repair, 120 of them waiting for a part.
        // Repair time is 20: counting the wait would hide a supply problem
        // behind a slow-looking maintenance team (ADR-051).
        $this->assertSame(120, $breakdown->hold_minutes);
        $this->assertSame(20, $breakdown->currentDowntime()->repair_minutes);

        CarbonImmutable::setTestNow();
    }

    public function test_an_out_of_order_timestamp_correction_is_refused(): void
    {
        CarbonImmutable::setTestNow('2026-08-18 09:00:00');
        $breakdown = $this->reported();
        $breakdown = $this->transition->acknowledge($breakdown, 'user-b');
        $breakdown = $this->transition->startRepair($breakdown, 'user-c');

        try {
            // Repair cannot have started before the machine broke.
            $this->transition->correctTimestamp(
                $breakdown, 'repair_started_at', CarbonImmutable::parse('2026-08-18 08:00:00'), 'user-c',
            );
            $this->fail('An out-of-order chain must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
        }

        CarbonImmutable::setTestNow();
    }

    public function test_a_backdated_failure_time_is_accepted_and_recorded(): void
    {
        CarbonImmutable::setTestNow('2026-08-18 09:00:00');
        $breakdown = $this->reported();

        // A machine that stopped an hour before anyone reported it is real and
        // common, so the stamp is editable — but the change is recorded, because
        // a downtime figure was derived from the old value.
        $corrected = $this->transition->correctTimestamp(
            $breakdown, 'failure_at', CarbonImmutable::parse('2026-08-18 08:00:00'), 'user-b',
        );

        $this->assertSame('2026-08-18 08:00:00', $corrected->failure_at->toDateTimeString());

        $note = $corrected->statusHistories()->first()->reason;
        $this->assertStringContainsString('08:00:00', $note);
        $this->assertStringContainsString('09:00:00', $note);

        CarbonImmutable::setTestNow();
    }

    public function test_closing_without_a_cause_is_refused(): void
    {
        $breakdown = $this->reported();
        $breakdown = $this->transition->acknowledge($breakdown, 'user-b');
        $breakdown = $this->transition->startRepair($breakdown, 'user-c');
        $breakdown = $this->transition->completeRepair($breakdown, 'user-c');

        try {
            // A breakdown closed with no recorded cause is a machine that broke
            // for no reason, and the failure reports are built from these fields.
            $this->transition->close($breakdown, [], 'user-b');
            $this->fail('Closing without a cause must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
            $this->assertArrayHasKey('failure_code_id', $e->errors());
            $this->assertArrayHasKey('root_cause_id', $e->errors());
        }

        $closed = $this->transition->close($breakdown->fresh(), [
            'failure_code_id' => FailureCode::where('code', 'BEARING_FAILURE')->firstOrFail()->id,
            'root_cause_id' => RootCause::where('code', 'INADEQUATE_LUBRICATION')->firstOrFail()->id,
            'corrective_action' => 'Bearing replaced and shaft realigned',
        ], 'user-b');

        $this->assertSame('CLOSED', $closed->status);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_completing_a_repair_that_never_started_is_refused(): void
    {
        $breakdown = $this->reported();
        $breakdown = $this->transition->acknowledge($breakdown, 'user-b');

        $this->expectException(ValidationException::class);
        $this->transition->completeRepair($breakdown, 'user-c');
    }

    public function test_a_transition_not_in_the_table_is_refused(): void
    {
        $breakdown = $this->reported();

        try {
            // Straight from REPORTED to repaired would mean nobody looked at it.
            $this->transition->completeRepair($breakdown, 'user-c');
            $this->fail('An unlisted transition must be refused.');
        } catch (ValidationException $e) {
            $this->assertContains($e->status, [409, 422]);
        }
    }

    public function test_a_technician_from_another_factory_cannot_be_assigned(): void
    {
        $gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');
        $elsewhere = WorkOrderFixture::technician(
            $this->delta, $gazipur, 'Jahangir Alam', 'EMP-2001',
        );

        $breakdown = $this->transition->acknowledge($this->reported(), 'user-b');

        $this->expectException(ValidationException::class);
        $this->transition->assign($breakdown, $elsewhere->id, 'user-b');
    }

    public function test_the_repair_work_order_links_back_and_does_not_double_count_the_stoppage(): void
    {
        $breakdown = $this->reported();

        $workOrder = app(RaiseBreakdownWorkOrder::class)->handle($breakdown, 'user-b');

        $this->assertSame($breakdown->id, $workOrder->breakdown_id);
        $this->assertSame('BREAKDOWN', $workOrder->source);
        // The machine is already stopped and the breakdown owns that unplanned
        // downtime. Marking the work order as requiring shutdown would count the
        // same stoppage a second time as planned downtime (ADR-049).
        $this->assertFalse($workOrder->requires_shutdown);
        $this->assertSame('NONE', $workOrder->downtime_class);
    }

    public function test_cancelling_needs_a_reason_and_returns_the_machine_to_service(): void
    {
        $breakdown = $this->reported();

        try {
            $this->transition->cancel($breakdown, '  ', 'user-b');
            $this->fail('An empty cancellation reason must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cancellation_reason', $e->errors());
        }

        $cancelled = $this->transition->cancel($breakdown->fresh(), 'Reported in error, wrong machine', 'user-b');

        $this->assertSame('CANCELLED', $cancelled->status);
        // A machine reported down in error should not stay flagged as broken.
        $this->assertSame('RUNNING', Asset::find($this->asset->id)->status);
    }

    public function test_every_transition_is_recorded_with_who_and_when(): void
    {
        $breakdown = $this->reported();
        $breakdown = $this->transition->acknowledge($breakdown, 'user-b');
        $breakdown = $this->transition->startRepair($breakdown, 'user-c');

        $history = $breakdown->statusHistories()->get();

        // Absent in v1.0, which left the breakdown lifecycle as the only major
        // workflow with no state audit trail.
        $this->assertSame(
            ['IN_REPAIR', 'ACKNOWLEDGED', 'REPORTED'],
            $history->pluck('to_status')->all(),
        );
        $this->assertSame('user-b', $history->firstWhere('to_status', 'ACKNOWLEDGED')->changed_by);
    }

    public function test_starting_repair_stamps_arrival_when_it_was_not_recorded(): void
    {
        $breakdown = $this->transition->acknowledge($this->reported(), 'user-b');

        $breakdown = $this->transition->startRepair($breakdown, 'user-c');

        // Requiring a separate tap for arrival means it gets skipped and the
        // response-time figure is then permanently null.
        $this->assertNotNull($breakdown->technician_arrival_at);
        $this->assertEquals(
            $breakdown->technician_arrival_at->timestamp,
            $breakdown->repair_started_at->timestamp,
        );
    }
}
