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
use App\Modules\WorkOrder\Models\LaborRateGrade;
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
 * Assignment and labour recording (SRS 13.2, ADR-050, ADR-065).
 *
 * The rule this file exists to pin down: the cost of internal work is derived
 * from a grade the server resolves, never from a rate a client sends. Anything
 * else lets whoever fills the form decide what the work cost, and turns a
 * maintenance record into a place colleagues' pay can be read off.
 */
class LaborAndAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private Factory $dhaka;

    private Factory $gazipur;

    private Asset $asset;

    private LaborRateGrade $grade;

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
        $this->grade = WorkOrderFixture::grade($this->delta, '120.0000');
        $this->technician = WorkOrderFixture::technician($this->delta, $this->dhaka, $this->grade);

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
            $this->delta, $this->gazipur, $this->grade, 'Jahangir Alam', 'EMP-2001',
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
            $this->delta, $this->dhaka, $this->grade, 'Sumon Sheikh', 'EMP-3001',
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
            $this->delta, $this->dhaka, $this->grade, 'Ruhul Amin', 'EMP-4001',
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

    public function test_the_rate_comes_from_the_grade_not_from_the_caller(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');
        $workOrder = $this->transition->start($workOrder, 'user-a');

        $entry = $this->labor->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 11:30:00'),
            technician: $this->technician,
            // Offered and ignored: only EXTERNAL labour carries a supplied rate.
            externalRate: '99999',
            userId: 'user-a',
        );

        $this->assertSame(150, $entry->minutes);
        $this->assertSame('120.0000', $entry->hourly_rate);
        // 2.5 hours at 120.
        $this->assertSame('300.0000', $entry->amount);
    }

    public function test_overtime_applies_the_grade_multiplier(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');
        $workOrder = $this->transition->start($workOrder, 'user-a');

        $entry = $this->labor->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 22:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 23:00:00'),
            category: 'OVERTIME',
            technician: $this->technician,
            userId: 'user-a',
        );

        $this->assertSame('240.0000', $entry->hourly_rate);
        $this->assertSame('240.0000', $entry->amount);
    }

    public function test_external_labour_requires_the_invoiced_rate(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');
        $workOrder = $this->transition->start($workOrder, 'user-a');

        try {
            $this->labor->handle(
                workOrder: $workOrder,
                startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
                endedAt: CarbonImmutable::parse('2026-08-17 10:00:00'),
                category: 'EXTERNAL',
                userId: 'user-a',
            );
            $this->fail('External labour with no rate must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('hourly_rate', $e->errors());
        }

        // A contractor's charge is an invoiced amount, not employee
        // compensation, so it is supplied rather than looked up.
        $entry = $this->labor->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 10:00:00'),
            category: 'EXTERNAL',
            externalRate: '850',
            userId: 'user-a',
        );

        $this->assertSame('850.0000', $entry->hourly_rate);
        $this->assertNull($entry->technician_id);
    }

    public function test_an_ungraded_technician_cannot_have_time_costed(): void
    {
        $ungraded = Technician::create([
            'company_id' => $this->delta->id,
            'factory_id' => $this->dhaka->id,
            'employee_id' => 'EMP-9001',
            'name' => 'Abdul Karim',
            'status' => 'ACTIVE',
        ]);

        $workOrder = $this->assign->handle($this->workOrder(), [$ungraded->id], 'user-a');
        $workOrder = $this->transition->start($workOrder, 'user-a');

        // Better to refuse than to record a zero cost that quietly understates
        // what maintenance costs.
        $this->expectException(ValidationException::class);
        $this->labor->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 10:00:00'),
            technician: $ungraded,
            userId: 'user-a',
        );
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

    public function test_the_actual_cost_is_derived_from_the_entries_and_updates_on_delete(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');
        $workOrder = $this->transition->start($workOrder, 'user-a');

        $first = $this->labor->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 10:00:00'),
            technician: $this->technician,
            userId: 'user-a',
        );

        $this->labor->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 14:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 15:00:00'),
            technician: $this->technician,
            userId: 'user-a',
        );

        $this->assertSame('240.0000', $workOrder->fresh()->actual_labor_cost);
        $this->assertSame('240.0000', $workOrder->fresh()->actual_cost);

        $this->labor->delete($first);

        // A total that disagrees with its own lines is worse than no total,
        // because someone makes a repair-versus-replace call on it (ADR-064).
        $this->assertSame('120.0000', $workOrder->fresh()->actual_labor_cost);
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

    public function test_a_rate_change_does_not_rewrite_the_cost_of_recorded_work(): void
    {
        $workOrder = $this->assign->handle($this->workOrder(), [$this->technician->id], 'user-a');
        $workOrder = $this->transition->start($workOrder, 'user-a');

        $entry = $this->labor->handle(
            workOrder: $workOrder,
            startedAt: CarbonImmutable::parse('2026-08-17 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-08-17 10:00:00'),
            technician: $this->technician,
            userId: 'user-a',
        );

        // The grade's rate rises. Historical cost must stay reproducible, so the
        // rate is copied onto the entry rather than looked up when read.
        $this->grade->forceFill(['standard_hourly_rate' => '200.0000'])->save();

        $this->assertSame('120.0000', $entry->fresh()->hourly_rate);
        $this->assertSame('120.0000', $entry->fresh()->amount);
    }

    public function test_no_technician_record_holds_a_wage(): void
    {
        // ADR-065 in one assertion: this is a maintenance system, and nobody
        // reading a cost breakdown should be able to derive a colleague's pay.
        $columns = Schema::getColumnListing('technicians');

        foreach (['salary', 'wage', 'basic_pay', 'bonus', 'payroll_id', 'hourly_rate'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }
}
