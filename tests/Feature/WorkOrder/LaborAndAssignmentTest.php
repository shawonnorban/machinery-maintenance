<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Modules\Asset\Models\Asset;
use App\Modules\Maintenance\Models\MaintenanceType;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Factory;
use App\Modules\WorkOrder\Actions\AssignTechnicians;
use App\Modules\WorkOrder\Actions\CreateWorkOrder;
use App\Modules\WorkOrder\Actions\RecordLaborEntry;
use App\Modules\WorkOrder\Actions\TransitionWorkOrder;
use App\Modules\WorkOrder\Models\Technician;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderAssignment;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\TenantFixture;
use Tests\Support\WorkOrderFixture;
use Tests\TestCase;

/**
 * Assignment and time recording (SRS 13.2, ADR-050).
 *
 * Technicians are salaried employees, so their hours carry no cost and none is
 * recorded. What these entries answer is who did the work and how long it
 * took, and the rules that keep those answers meaningful: one person cannot be
 * in two places at once, and time cannot be logged against a job that is
 * already closed.
 */
class LaborAndAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    private Asset $asset;

    private Technician $technician;

    private RecordLaborEntry $labor;

    private AssignTechnicians $assign;

    private TransitionWorkOrder $transition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        $this->dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $this->gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GAZ');
        TenantFixture::actingAsTenant($this->delta);

        $this->asset = WorkOrderFixture::runningAsset($this->delta, $this->dhaka);
        $this->technician = WorkOrderFixture::technician($this->delta, $this->dhaka);

        $this->labor = app(RecordLaborEntry::class);
        $this->assign = app(AssignTechnicians::class);
        $this->transition = app(TransitionWorkOrder::class);
    }

    private function workOrder(): WorkOrder
    {
        $workOrder = app(CreateWorkOrder::class)->handle([
            'asset_id' => $this->asset->id,
            'maintenance_type_id' => MaintenanceType::where('code', 'PREVENTIVE')->firstOrFail()->id,
            'title' => 'Monthly service',
        ], 'user-a');

        return $this->transition->schedule($workOrder, 'user-a');
    }

    public function test_assigning_a_technician_advances_a_scheduled_job(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');

        $this->assertSame('ASSIGNED', $workOrder->status);
        $this->assertSame(1, $workOrder->activeAssignments()->count());
    }

    public function test_a_technician_from_another_factory_is_refused(): void
    {
        $elsewhere = WorkOrderFixture::technician(
            $this->delta, $this->gazipur, 'Jahangir Alam', 'EMP-2001',
        );

        // Sending someone to another site is a decision with travel attached; it
        // is not something an assignment dropdown should do by accident.
        $this->expectException(ValidationException::class);
        $this->assign->handle($this->workOrder(), [$elsewhere->id], 'user-a');
    }

    public function test_an_inactive_technician_is_refused(): void
    {
        $this->technician->forceFill(['status' => 'INACTIVE'])->save();

        $this->expectException(ValidationException::class);
        $this->assign->handle($this->workOrder(), [$this->technician->fresh()->id], 'user-a');
    }

    public function test_a_technician_at_their_concurrent_limit_is_refused(): void
    {
        $limited = WorkOrderFixture::technician(
            $this->delta, $this->dhaka, 'Sumon Sheikh', 'EMP-3001',
            maxConcurrent: 1,
        );

        $this->assign->handle($this->workOrder(), [$limited->id], 'user-a');

        try {
            // A queue of jobs against one person reads as scheduled work while
            // none of it is moving.
            $this->assign->handle($this->workOrder(), [$limited->id], 'user-a');
            $this->fail('Assigning past the concurrent limit must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_removing_the_last_technician_returns_the_job_to_the_queue(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');

        $workOrder = $this->assign->unassign($workOrder, $this->technician->id, 'user-a');

        // An ASSIGNED job with nobody on it is a job nobody will do.
        $this->assertSame('SCHEDULED', $workOrder->status);
    }

    public function test_a_reassignment_ends_the_previous_assignment_rather_than_deleting_it(): void
    {
        $second = WorkOrderFixture::technician(
            $this->delta, $this->dhaka, 'Ruhul Amin', 'EMP-4001',
        );

        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');
        $this->assign->handle($workOrder, [$second->id], 'user-b');

        // "Who was on this job" stays answerable after a handover, which is
        // usually the interesting part.
        $this->assertSame(2, WorkOrderAssignment::where('work_order_id', $workOrder->id)->count());
        $this->assertSame(1, $workOrder->fresh()->activeAssignments()->count());

        $ended = WorkOrderAssignment::where('work_order_id', $workOrder->id)
            ->where('technician_id', $this->technician->id)
            ->firstOrFail();

        $this->assertNotNull($ended->unassigned_at);
    }

    public function test_overlapping_time_for_one_technician_is_refused(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');
        $workOrder = $this->transition->start($workOrder, 'user-a');

        $this->labor->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 11:00:00'),
            technician: $this->technician,
            userId: 'user-a',
        );

        try {
            // One person cannot be in two places at once, and an overlap makes
            // every utilisation figure it touches meaningless.
            $this->labor->handle(
                workOrder: $workOrder,
                startedAt: CarbonImmutable::parse('2026-08-17 10:00:00'),
                endedAt: CarbonImmutable::parse('2026-08-17 12:00:00'),
                technician: $this->technician,
                userId: 'user-a',
            );
            $this->fail('Overlapping labour must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_labour_in_the_future_and_longer_than_a_day_is_refused(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');
        $workOrder = $this->transition->start($workOrder, 'user-a');

        try {
            $this->labor->handle(
                workOrder: $workOrder,
                startedAt: CarbonImmutable::now()->addHours(2),
                endedAt: CarbonImmutable::now()->addHours(3),
                technician: $this->technician,
                userId: 'user-a',
            );
            $this->fail('Future labour must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ended_at', $e->errors());
        }

        try {
            // A single entry longer than a day is a data-entry slip, not a shift.
            $this->labor->handle(
                workOrder: $workOrder,
                startedAt: CarbonImmutable::parse('2026-08-10 08:00:00'),
                endedAt: CarbonImmutable::parse('2026-08-12 08:00:00'),
                technician: $this->technician,
                userId: 'user-a',
            );
            $this->fail('A two-day labour entry must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ended_at', $e->errors());
        }
    }

    public function test_a_closed_work_order_accepts_no_more_labour(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');
        $workOrder = $this->transition->start($workOrder, 'user-a');
        $workOrder = $this->transition->complete($workOrder, 'user-a');
        $workOrder = $this->transition->close($workOrder, 'user-b');

        try {
            $this->labor->handle(
                workOrder: $workOrder,
                startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
                endedAt: CarbonImmutable::parse('2026-08-17 10:00:00'),
                technician: $this->technician,
                userId: 'user-a',
            );
            $this->fail('Labour on a closed work order must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(409, $e->status);
        }
    }

    public function test_no_money_is_recorded_against_a_person_anywhere(): void
    {
        // This is a maintenance system, not a payroll one. Technicians are
        // salaried, so nothing about what they are paid — or what an hour of
        // their time is worth — belongs in either table.
        foreach (['salary', 'wage', 'basic_pay', 'bonus', 'payroll_id', 'hourly_rate', 'labor_grade_id'] as $forbidden) {
            $this->assertNotContains($forbidden, Schema::getColumnListing('technicians'));
        }

        foreach (['hourly_rate', 'amount', 'base_amount', 'currency', 'labor_grade_id'] as $forbidden) {
            $this->assertNotContains($forbidden, Schema::getColumnListing('work_order_labor_entries'));
        }
    }
}
